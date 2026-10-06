<?php

namespace App\Services\Assistant;

use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Answers about products: searching, details, availability, what's
 * popular or new, the categories, and "show more".
 */
class ProductReplies
{
    private const PAGE = 4;

    public function __construct(private AssistantCatalog $catalog, private AssistantContext $context) {}

    public function search(Understanding $u): Reply
    {
        $criteria = $this->criteria($u);

        // "under $50", "in blue" or "best rated" on their own refine the last search.
        if ($this->isRefinement($u) && ($previous = $this->context->search())) {
            $criteria = [...$previous['criteria'], ...array_filter($criteria, fn ($v) => $v !== null && $v !== '')];
        }

        return $this->results($criteria, 0, $u);
    }

    public function more(Understanding $u): Reply
    {
        $previous = $this->context->search();

        if (! $previous) {
            return Reply::say("There's nothing to show more of yet. Tell me what you're looking for, for example “wireless headphones” or “a gift under \$50”.")
                ->suggest(...$this->starters());
        }

        $criteria = $previous['criteria'];
        $offset = $previous['offset'];

        // "cheaper ones" re-sorts from the start; "show more" carries on.
        if ($u->entity('refine')) {
            $criteria['sort'] = 'price_asc';
            $offset = 0;
        }

        return $this->results($criteria, $offset, $u);
    }

    public function details(Understanding $u): Reply
    {
        $product = $this->resolve($u);

        if (! $product) {
            return $this->unknownProduct($u);
        }

        $this->context->rememberProduct($product->slug);

        $text = "{$product->name} costs ".$this->money($product->price_cents).'.';

        if ($product->reviews_count > 0) {
            $text .= ' Customers rate it '.round((float) $product->reviews_avg_rating, 1).' out of 5 from '
                .$product->reviews_count.($product->reviews_count === 1 ? ' review.' : ' reviews.');
        }

        $text .= ' '.$this->stockSentence($product);

        if ($product->description) {
            $text .= "\n\n".Str::limit(trim(preg_replace('/\s+/', ' ', $product->description)), 220);
        }

        $reply = Reply::say($text)->products([$this->catalog->card($product)]);

        return $product->hasVariants()
            ? $reply->suggest('What sizes and colours are available?')
            : $reply;
    }

    public function availability(Understanding $u): Reply
    {
        $product = $this->resolve($u);

        if (! $product) {
            return $this->unknownProduct($u);
        }

        $this->context->rememberProduct($product->slug);

        $size = $u->entity('size');
        $color = $u->entity('color');

        $text = match (true) {
            $product->hasVariants() => $this->variantAvailability($product, $size, $color),
            // A size or colour was asked for, but this product has no options.
            (bool) ($size || $color) => "{$product->name} doesn't come in different sizes or colours. ".$this->stockSentence($product),
            default => $this->stockSentence($product, withName: true),
        };

        return Reply::say($text)->products([$this->catalog->card($product)]);
    }

    public function bestSellers(): Reply
    {
        $products = $this->catalog->bestSellers(self::PAGE);

        if ($products->isEmpty()) {
            return Reply::say("I don't have enough sales yet to say what's popular. Try browsing the categories.")->suggest('What categories do you have?');
        }

        $this->context->rememberSearch(['criteria' => [], 'offset' => self::PAGE], $products->pluck('slug')->all());

        return Reply::say('These are the most popular right now:')
            ->products($products->map(fn (Product $p) => $this->catalog->card($p))->all())
            ->suggest('Show me the newest products', 'What categories do you have?');
    }

    public function newArrivals(): Reply
    {
        $products = $this->catalog->newest(self::PAGE);

        if ($products->isEmpty()) {
            return Reply::say("There's nothing new in stock at the moment.");
        }

        $this->context->rememberSearch(['criteria' => ['sort' => 'newest'], 'offset' => self::PAGE], $products->pluck('slug')->all());

        return Reply::say('These are the newest products in stock:')
            ->products($products->map(fn (Product $p) => $this->catalog->card($p))->all())
            ->suggest('Show more', "What's popular right now?");
    }

    public function categories(): Reply
    {
        $categories = collect($this->catalog->categories())->where('products', '>', 0);

        if ($categories->isEmpty()) {
            return Reply::say("There aren't any products on sale yet.");
        }

        $list = $categories->map(fn (array $c) => "{$c['name']} ({$c['products']})")->implode(', ');

        return Reply::say("Here's what we sell: {$list}. Tell me which one you'd like to look at.")
            ->suggest(...$categories->take(4)->map(fn (array $c) => "Show me {$c['name']}")->all());
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    private function results(array $criteria, int $offset, Understanding $u): Reply
    {
        $result = $this->catalog->search($criteria, self::PAGE, $offset);
        $products = $result['products'];

        if ($products->isEmpty()) {
            return $this->nothingFound($criteria, $result, $offset);
        }

        $shown = $offset + $products->count();
        $this->context->rememberSearch(['criteria' => $criteria, 'offset' => $shown], $products->pluck('slug')->all());

        $text = $offset > 0 ? 'Here are some more' : "Here's what I found";
        $text .= $this->describe($criteria, $result).'.';

        if ($result['relaxed'] && $result['asked'] !== '') {
            $text .= " I couldn't match “{$result['asked']}” exactly, so I searched for “{$result['query']}”.";
        } elseif ($result['corrected']) {
            $text .= " I searched for “{$result['query']}” instead of “{$result['asked']}”.";
        }

        if ($result['total'] > $shown) {
            $text .= " Showing {$shown} of {$result['total']}.";
        }

        $reply = Reply::say($text)->products($products->map(fn (Product $p) => $this->catalog->card($p))->all());

        if ($result['total'] > $shown) {
            $reply->suggest('Show more');
        }

        if (($criteria['sort'] ?? null) !== 'price_asc') {
            $reply->suggest('Cheapest first');
        }

        if (($criteria['sort'] ?? null) !== 'rating') {
            $reply->suggest('Best rated');
        }

        return $reply;
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $result
     */
    private function nothingFound(array $criteria, array $result, int $offset): Reply
    {
        if ($offset > 0) {
            return Reply::say("That's everything I have for that search.")->suggest(...$this->starters());
        }

        $what = $result['asked'] !== '' ? " for “{$result['asked']}”" : '';
        $text = "I couldn't find anything{$what}".$this->describe($criteria, [...$result, 'query' => '']).'.';

        $filters = array_intersect_key($criteria, array_flip(['category', 'min_price_cents', 'max_price_cents', 'color', 'size']));

        $text .= $filters !== []
            ? ' Try loosening the filters, or ask for something different.'
            : ' Check the spelling, or try a more general word. You can also browse by category.';

        return Reply::say($text)->suggest('What categories do you have?', "What's popular right now?");
    }

    /**
     * " for “headphones” in Electronics under $50 (cheapest first)"
     *
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $result
     */
    private function describe(array $criteria, array $result): string
    {
        $text = '';

        if (! empty($result['query']) && ! $result['relaxed'] && ! $result['corrected']) {
            $text .= " for “{$result['query']}”";
        }

        if ($slug = $criteria['category'] ?? null) {
            $name = collect($this->catalog->categories())->firstWhere('slug', $slug)['name'] ?? $slug;
            $text .= " in {$name}";
        }

        if ($color = $criteria['color'] ?? null) {
            $text .= " in {$color}";
        }

        if ($size = $criteria['size'] ?? null) {
            $text .= " in size {$size}";
        }

        if (($criteria['min_price_cents'] ?? null) && ($criteria['max_price_cents'] ?? null)) {
            $text .= ' between '.$this->money($criteria['min_price_cents']).' and '.$this->money($criteria['max_price_cents']);
        } elseif ($max = $criteria['max_price_cents'] ?? null) {
            $text .= ' under '.$this->money($max);
        } elseif ($min = $criteria['min_price_cents'] ?? null) {
            $text .= ' over '.$this->money($min);
        }

        $text .= match ($criteria['sort'] ?? null) {
            'price_asc' => ' (cheapest first)',
            'price_desc' => ' (most expensive first)',
            'rating' => ' (best rated first)',
            'newest' => ' (newest first)',
            default => '',
        };

        return $text;
    }

    private function variantAvailability(Product $product, ?string $size, ?string $color): string
    {
        $variants = $product->variants;
        $name = $product->name;

        $matching = $variants->filter(fn ($v) => (! $size || strcasecmp($v->size, $size) === 0) && (! $color || strcasecmp($v->color, $color) === 0));
        $available = $matching->where('stock', '>', 0);

        // Asked about a specific size and/or colour.
        if ($size || $color) {
            $asked = collect([$size ? "size {$size}" : null, $color])->filter()->implode(' in ');

            if ($matching->isEmpty()) {
                return "{$name} doesn't come in {$asked}. ".$this->optionsSentence($product);
            }

            if ($available->isNotEmpty()) {
                $first = $available->first();
                $left = $first->stock <= 5 && $available->count() === 1 ? " Only {$first->stock} left." : '';

                $more = $available->count() > 1 ? ' Options in stock: '.$available->pluck('label')->implode(', ').'.' : '';

                return "Yes, {$name} is in stock in {$asked}.{$left}{$more}";
            }

            $others = $variants->where('stock', '>', 0);

            return "{$name} is sold out in {$asked}."
                .($others->isNotEmpty() ? ' Still in stock: '.$others->pluck('label')->implode(', ').'.' : ' Every option is sold out right now.');
        }

        if ($product->stock <= 0) {
            return "{$name} is sold out in every option right now.";
        }

        return $this->optionsSentence($product);
    }

    private function optionsSentence(Product $product): string
    {
        $variants = $product->variants;
        $sizes = $variants->pluck('size')->filter()->unique()->values();
        $colors = $variants->pluck('color')->filter()->unique()->values();

        $parts = [];
        $sizes->isNotEmpty() && $parts[] = 'sizes '.$sizes->implode(', ');
        $colors->isNotEmpty() && $parts[] = 'colours '.$colors->implode(', ');

        $soldOut = $variants->where('stock', '<=', 0);
        $text = "It comes in {$this->join($parts)}.";

        if ($soldOut->isNotEmpty() && $soldOut->count() < $variants->count()) {
            $text .= ' Sold out right now: '.$soldOut->pluck('label')->implode(', ').'.';
        }

        return $text;
    }

    private function stockSentence(Product $product, bool $withName = false): string
    {
        $subject = $withName ? $product->name : 'It';

        return match (true) {
            $product->stock <= 0 => "{$subject} is sold out right now.",
            $product->stock <= 5 && ! $product->hasVariants() => "{$subject} is in stock, but only {$product->stock} are left.",
            default => "{$subject} is in stock.".($product->hasVariants() ? ' '.$this->optionsSentence($product) : ''),
        };
    }

    /**
     * The product the message is about: "the second one", a named product, or
     * the one we were just talking about.
     */
    private function resolve(Understanding $u): ?Product
    {
        $ordinal = $u->entity('ordinal');

        if ($ordinal !== null && ($shown = $this->context->shown()) !== []) {
            $slug = $ordinal === -1 ? end($shown) : ($shown[$ordinal] ?? null);

            if ($slug && ($product = $this->catalog->bySlug($slug))) {
                return $product;
            }
        }

        $query = (string) $u->entity('query', '');

        if ($query !== '') {
            return $this->catalog->find($query);
        }

        $slug = $this->context->product();

        return $slug ? $this->catalog->bySlug($slug) : null;
    }

    private function unknownProduct(Understanding $u): Reply
    {
        $query = (string) $u->entity('query', '');

        $text = $query !== ''
            ? "I couldn't find a product called “{$query}”. Try its full name, or search for it."
            : 'Which product do you mean? Tell me its name, or search for something first.';

        return Reply::say($text)->suggest(...$this->starters());
    }

    /**
     * @return array<string, mixed>
     */
    private function criteria(Understanding $u): array
    {
        return array_filter([
            'query' => $u->entity('query'),
            'category' => $u->entity('category'),
            'min_price_cents' => $u->entity('min_price_cents'),
            'max_price_cents' => $u->entity('max_price_cents'),
            'sort' => $u->entity('sort'),
            'size' => $u->entity('size'),
            'color' => $u->entity('color'),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * A message with no product words, only filters, adds to the last search.
     */
    private function isRefinement(Understanding $u): bool
    {
        return (string) $u->entity('query', '') === '' && ! $u->entity('category') && ! $u->entity('explicit');
    }

    /**
     * @return list<string>
     */
    private function starters(): array
    {
        return ['Find a gift under $50', "What's popular right now?", 'What categories do you have?'];
    }

    private function money(int $cents): string
    {
        return '$'.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
    }

    /**
     * @param  list<string>  $parts
     */
    private function join(array $parts): string
    {
        return implode(' and ', $parts);
    }
}
