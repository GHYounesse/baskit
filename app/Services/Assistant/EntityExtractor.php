<?php

namespace App\Services\Assistant;

use App\Services\Search\SearchTerms;
use Illuminate\Support\Str;

/**
 * Pulls the useful details out of a message: a price limit, a size, a colour,
 * a category, an order number, and what is left over as the product being
 * talked about. It works on text the classifier has already tidied up.
 */
class EntityExtractor
{
    private const NUMBER = '\$?\s*(\d[\d,]*(?:\.\d+)?)';

    private const SIZE_WORDS = [
        'extra small' => 'XS', 'x-small' => 'XS', 'small' => 'S', 'medium' => 'M',
        'extra large' => 'XL', 'x-large' => 'XL', 'large' => 'L',
    ];

    private const LETTER_SIZES = ['xxs', 'xs', 's', 'm', 'l', 'xl', 'xxl', 'xxxl'];

    /**
     * Words that say what someone wants to do rather than what they want.
     */
    private const FILLER = [
        'a', 'an', 'the', 'i', 'me', 'my', 'we', 'you', 'your', 'yours', 'do', 'does', 'did', 'have', 'has', 'got',
        'any', 'some', 'for', 'with', 'in', 'on', 'of', 'to', 'is', 'are', 'was', 'be', 'can', 'could', 'would',
        'will', 'please', 'pls', 'find', 'show', 'search', 'looking', 'look', 'need', 'want', 'wanna', 'get', 'buy',
        'recommend', 'suggest', 'suggestion', 'suggestions', 'something', 'anything', 'available', 'availability',
        'stock', 'size', 'colour', 'color', 'price', 'prices', 'cost', 'much', 'how', 'what', 'whats', 'which',
        'who', 'where', 'under', 'below', 'above', 'over', 'than', 'less', 'more', 'cheap', 'cheaper', 'cheapest',
        'expensive', 'best', 'top', 'rated', 'new', 'newest', 'latest', 'tell', 'about', 'details', 'detail',
        'info', 'information', 'ones', 'one', 'it', 'its', 'this', 'that', 'these', 'those', 'there', 'store',
        'shop', 'dollars', 'dollar', 'usd', 'between', 'and', 'or', 'around', 'up', 'max', 'maximum', 'min',
        'minimum', 'least', 'most', 'im', 'ive', 'id', 'like', 'also', 'then', 'just', 'only', 'really', 'very',
        'good', 'nice', 'sold', 'out', 'left', 'budget', 'gift', 'but', 'so', 'no', 'not', 'yes', 'yeah', 'ok',
        'okay', 'sizes', 'colours', 'colors', 'options', 'option', 'variants', 'first', 'second', 'third', 'fourth', 'last', 'hi', 'hello', 'hey', 'thanks', 'thank', 'order', 'orders', 'review', 'reviews', 'rating',
        'ratings', 'worth', 'describe', 'description', 'if', 'when', 'why', 'can\'t', 'cant', 'dont', 'don\'t',
    ];

    public function __construct(private AssistantCatalog $catalog) {}

    /**
     * @param  string  $text  lower-case, apostrophes straightened
     * @return array<string, mixed>
     */
    public function extract(string $text): array
    {
        $entities = [];
        $remaining = $text;

        // Order number: "order 12", "order #12", "#12".
        if (preg_match('/(?:\border\s*(?:number|no\.?|id)?\s*#?\s*|#)(\d{1,9})\b/', $remaining, $m)) {
            $entities['order_id'] = (int) $m[1];
            $remaining = str_replace($m[0], ' ', $remaining);
        }

        [$min, $max, $remaining] = $this->prices($remaining);
        $min !== null && $entities['min_price_cents'] = $min;
        $max !== null && $entities['max_price_cents'] = $max;

        if ($sort = $this->sort($text)) {
            $entities['sort'] = $sort;
        }

        [$size, $remaining] = $this->size($remaining);
        $size !== null && $entities['size'] = $size;

        [$color, $remaining] = $this->color($remaining);
        $color !== null && $entities['color'] = $color;

        [$category, $remaining] = $this->category($remaining);
        $category !== null && $entities['category'] = $category;

        if (preg_match('/\b(it|this|that|these|those|them|one)\b/', $text)) {
            $entities['pronoun'] = true;
        }

        if (preg_match('/\b(first|1st|second|2nd|third|3rd|fourth|4th|last)\b/', $text, $m)) {
            $entities['ordinal'] = ['first' => 0, '1st' => 0, 'second' => 1, '2nd' => 1, 'third' => 2, '3rd' => 2, 'fourth' => 3, '4th' => 3, 'last' => -1][$m[1]];
        }

        // "find me...", "recommend..." start a new search; bare filters ("under $50") refine the last one.
        $entities['explicit'] = (bool) preg_match('/\b(find|show|search|looking for|look for|i need|i want|i\'?d like|recommend|suggest|gift|something|anything|got any|do you (have|sell|carry))\b/', $text);

        $entities['query'] = $this->subject($remaining);

        return $entities;
    }

    /**
     * @return array{0: ?int, 1: ?int, 2: string} minimum and maximum in cents, and the text without the price words
     */
    private function prices(string $text): array
    {
        $min = $max = null;

        if (preg_match('/between\s+'.self::NUMBER.'\s*(?:and|to|-)\s*'.self::NUMBER.'/', $text, $m)) {
            $a = $this->cents($m[1]);
            $b = $this->cents($m[2]);
            [$min, $max] = [min($a, $b), max($a, $b)];
            $text = str_replace($m[0], ' ', $text);
        }

        if ($max === null && preg_match('/(?:under|below|less than|cheaper than|up to|max(?:imum)?(?: of)?|no more than|not more than|within|budget(?: of)?|for)\s+'.self::NUMBER.'(?!\s*(?:pcs|pieces|pack))/', $text, $m)) {
            $max = $this->cents($m[1]);
            $text = str_replace($m[0], ' ', $text);
        }

        if ($min === null && preg_match('/(?:over|above|more than|at least|min(?:imum)?(?: of)?)\s+'.self::NUMBER.'/', $text, $m)) {
            $min = $this->cents($m[1]);
            $text = str_replace($m[0], ' ', $text);
        }

        // A bare "$50" still reads as a price.
        $text = preg_replace('/\$\s*\d[\d,]*(?:\.\d+)?/', ' ', $text);

        return [$min, $max, $text];
    }

    private function cents(string $number): int
    {
        return (int) round(((float) str_replace(',', '', $number)) * 100);
    }

    private function sort(string $text): ?string
    {
        return match (true) {
            (bool) preg_match('/\b(cheapest|lowest price|least expensive|most affordable|budget)\b/', $text) => 'price_asc',
            (bool) preg_match('/\b(most expensive|priciest|highest price|premium|high[- ]end)\b/', $text) => 'price_desc',
            (bool) preg_match('/\b(best[- ]rated|top[- ]rated|highest[- ]rated|best reviewed|best reviews)\b/', $text) => 'rating',
            (bool) preg_match('/\b(newest|latest)\b/', $text) => 'newest',
            default => null,
        };
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function size(string $text): array
    {
        foreach (self::SIZE_WORDS as $word => $size) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/', $text, $m)) {
                return [$size, str_replace($m[0], ' ', $text)];
            }
        }

        $letters = implode('|', self::LETTER_SIZES);

        // "size m", "size 42", "in xl", "in a medium".
        if (preg_match('/\bsize\s+('.$letters.'|\d{2})\b/', $text, $m) || preg_match('/\bin\s+(?:size\s+)?(xxs|xs|xl|xxl|xxxl|s|m|l|\d{2})\b/', $text, $m)) {
            return [strtoupper($m[1]), str_replace($m[0], ' ', $text)];
        }

        if (preg_match('/\b(xxs|xs|xl|xxl|xxxl)\b/', $text, $m)) {
            return [strtoupper($m[1]), str_replace($m[0], ' ', $text)];
        }

        return [null, $text];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function color(string $text): array
    {
        foreach ($this->catalog->knownColors() as $color) {
            if (preg_match('/\b'.preg_quote(mb_strtolower($color), '/').'\b/', $text, $m)) {
                return [$color, str_replace($m[0], ' ', $text)];
            }
        }

        return [null, $text];
    }

    /**
     * Matches a category by its name, its slug, or a distinctive word in the name
     * ("kitchen" finds "Home & Kitchen"), singular or plural.
     *
     * @return array{0: ?string, 1: string} the category slug, and the text without the category words
     */
    private function category(string $text): array
    {
        foreach ($this->catalog->categories() as $category) {
            $words = collect(preg_split('/[^a-z0-9]+/', mb_strtolower($category['name']), -1, PREG_SPLIT_NO_EMPTY))
                ->filter(fn (string $w) => strlen($w) >= 4)
                ->flatMap(fn (string $w) => [$w, Str::singular($w), Str::plural($w)])
                ->unique()
                ->all();

            $candidates = [mb_strtolower($category['name']), $category['slug'], ...$words];

            foreach ($candidates as $candidate) {
                if (preg_match('/\b'.preg_quote($candidate, '/').'\b/', $text, $m)) {
                    return [$category['slug'], str_replace($m[0], ' ', $text)];
                }
            }
        }

        return [null, $text];
    }

    /**
     * What is left once the doing-words are taken out: the thing being asked about.
     */
    private function subject(string $text): string
    {
        $words = array_filter(
            SearchTerms::words(str_replace("'", '', $text)),
            fn (string $word) => ! in_array($word, self::FILLER, true) && ! ctype_digit($word),
        );

        return implode(' ', $words);
    }
}
