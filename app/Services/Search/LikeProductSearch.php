<?php

namespace App\Services\Search;

use App\Models\Product;

/**
 * Portable product search for databases without FTS5 (MySQL, Postgres).
 * Slower on a large catalogue and less sophisticated, but it follows the
 * same rules: every word must match somewhere, and a hit in the name counts
 * for more than one in the category or description.
 *
 * It has no index of its own, so there is nothing to keep in sync.
 *
 * Accent handling is whatever the database's collation does (MySQL's
 * utf8mb4_unicode_ci treats "cafe" and "café" alike; SQLite's LIKE does not).
 */
class LikeProductSearch implements ProductSearch
{
    /**
     * How many matching rows are fetched to be scored. Ranking happens in PHP,
     * so this bounds the work.
     */
    private const CANDIDATES = 500;

    public function ids(string $query, int $limit = 200): array
    {
        $terms = SearchTerms::from($query);

        if ($terms === []) {
            return [];
        }

        $candidates = Product::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->where(fn ($q) => $q
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$term}%")));
                }
            })
            ->limit(self::CANDIDATES)
            ->get(['id', 'name', 'description', 'category_id']);

        return $candidates
            ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'score' => $this->score($p, $terms)])
            ->sortBy([['score', 'desc'], ['name', 'asc']])
            ->take($limit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * A word at the start of a word in the name is the best hit, then
     * anywhere in the name, then the category, then the description.
     *
     * @param  list<string>  $terms
     */
    private function score(Product $product, array $terms): int
    {
        $name = strtolower($product->name);
        $category = strtolower($product->category?->name ?? '');
        $description = strtolower((string) $product->description);

        $score = 0;

        foreach ($terms as $term) {
            $score += match (true) {
                preg_match('/\b'.preg_quote($term, '/').'/', $name) === 1 => 10,
                str_contains($name, $term) => 6,
                str_contains($category, $term) => 3,
                str_contains($description, $term) => 1,
                default => 0,
            };
        }

        return $score;
    }

    public function index(Product $product): void {}

    public function remove(int $productId): void {}

    public function reindex(): int
    {
        return 0;
    }
}
