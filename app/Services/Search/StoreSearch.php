<?php

namespace App\Services\Search;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * What the shop's search box and results page use. It sits on top of a
 * ProductSearch engine and adds the shop-level pieces: turning ranked IDs
 * into products, matching categories, and suggesting a corrected spelling.
 */
class StoreSearch
{
    private const VOCABULARY_KEY = 'search.vocabulary';

    public function __construct(private ProductSearch $engine) {}

    /**
     * Ranked IDs of the active products matching the query.
     *
     * @return list<int>
     */
    public function ids(string $query, int $limit = 200): array
    {
        return $this->engine->ids($query, $limit);
    }

    /**
     * What the autocomplete dropdown shows: the best few products, matching
     * categories, how many products match in all, and (only when nothing
     * matched) a corrected spelling to offer instead.
     *
     * @return array{query: string, total: int, products: list<array<string, mixed>>, categories: list<array<string, mixed>>, correction: ?string}
     */
    public function suggest(string $query, int $productLimit = 6, int $categoryLimit = 3): array
    {
        $ids = $this->ids($query);
        $products = $this->hydrate(array_slice($ids, 0, $productLimit));

        return [
            'query' => $query,
            'total' => count($ids),
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price_cents' => $p->price_cents,
                'image_url' => $p->image_url,
                'category' => $p->category?->name,
            ])->all(),
            'categories' => $this->categories($query, $categoryLimit)
                ->map(fn (Category $c) => ['name' => $c->name, 'slug' => $c->slug])
                ->all(),
            'correction' => $ids === [] ? $this->correction($query) : null,
        ];
    }

    /**
     * Categories whose name contains every word typed ("kitchen" finds "Home & Kitchen").
     *
     * @return Collection<int, Category>
     */
    public function categories(string $query, int $limit = 3): Collection
    {
        $terms = SearchTerms::from($query);

        if ($terms === []) {
            return collect();
        }

        return Category::query()
            ->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->where('name', 'like', "%{$term}%");
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * "headphnes" -> "headphones". Each word that isn't the start of any word
     * in the catalogue is swapped for the closest catalogue word within a
     * small edit distance. Null when nothing needed fixing or nothing is close.
     */
    public function correction(string $query): ?string
    {
        $vocabulary = $this->vocabulary();
        $changed = false;
        $words = [];

        foreach (SearchTerms::from($query) as $term) {
            $fixed = $this->closestWord($term, $vocabulary);

            $changed = $changed || $fixed !== $term;
            $words[] = $fixed;
        }

        return $changed ? implode(' ', $words) : null;
    }

    /**
     * Forget the cached spelling vocabulary. Called whenever products or
     * categories change.
     */
    public static function forgetVocabulary(): void
    {
        Cache::forget(self::VOCABULARY_KEY);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    private function hydrate(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Product::with('category')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Product $p) => array_search($p->id, $ids, true))
            ->values();
    }

    /**
     * @param  list<string>  $vocabulary
     */
    private function closestWord(string $term, array $vocabulary): string
    {
        // Too short to correct sensibly, or already the start of a real word
        // (which is what someone mid-typing looks like).
        if (strlen($term) < 4) {
            return $term;
        }

        foreach ($vocabulary as $word) {
            if (str_starts_with($word, $term)) {
                return $term;
            }
        }

        $allowed = strlen($term) <= 5 ? 1 : 2;
        $best = $term;
        $bestDistance = $allowed + 1;

        foreach ($vocabulary as $word) {
            if (abs(strlen($word) - strlen($term)) > $allowed) {
                continue;
            }

            $distance = levenshtein($term, $word);

            if ($distance < $bestDistance) {
                $best = $word;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * Every word (3+ letters) used in an active product name or category name.
     * Cached for a few minutes; cleared when the catalogue changes.
     *
     * @return list<string>
     */
    private function vocabulary(): array
    {
        return Cache::remember(self::VOCABULARY_KEY, 600, function () {
            $names = Product::where('is_active', true)->pluck('name')
                ->merge(Category::pluck('name'));

            return $names
                ->flatMap(fn (string $name) => SearchTerms::words($name))
                ->filter(fn (string $word) => strlen($word) >= 3)
                ->unique()
                ->values()
                ->all();
        });
    }
}
