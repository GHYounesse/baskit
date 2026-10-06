<?php

namespace App\Services\Search;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Product search on SQLite's FTS5 full-text index (the `product_search`
 * virtual table).
 *
 * Every word must match (AND), and the last word is a prefix match, so
 * "wireless head" finds "Wireless Headphones". Results are ranked with BM25,
 * counting a hit in the name 10 times, in the category 4 times and in the
 * description once, so a product named "Speaker" beats one that only
 * mentions speakers in passing.
 */
class FtsProductSearch implements ProductSearch
{
    public function ids(string $query, int $limit = 200): array
    {
        $terms = SearchTerms::from($query);

        if ($terms === []) {
            return [];
        }

        // Safe to build by hand: SearchTerms leaves only [a-z0-9] in each word,
        // so nothing here can be read as FTS5 syntax. Each word is quoted and
        // prefix-matched.
        $match = implode(' ', array_map(fn (string $term) => "\"{$term}\"*", $terms));

        $rows = DB::select(
            'SELECT product_search.product_id AS id
             FROM product_search
             JOIN products ON products.id = product_search.product_id
             WHERE product_search MATCH ? AND products.is_active = 1
             ORDER BY bm25(product_search, 0.0, 10.0, 4.0, 1.0), products.name
             LIMIT ?',
            [$match, $limit],
        );

        return array_map(fn ($row) => (int) $row->id, $rows);
    }

    public function index(Product $product): void
    {
        $this->remove($product->id);

        DB::insert(
            'INSERT INTO product_search (product_id, name, category, description) VALUES (?, ?, ?, ?)',
            [$product->id, $product->name, $product->category?->name ?? '', $product->description ?? ''],
        );
    }

    public function remove(int $productId): void
    {
        DB::delete('DELETE FROM product_search WHERE product_id = ?', [$productId]);
    }

    public function reindex(): int
    {
        DB::delete('DELETE FROM product_search');

        $count = 0;

        Product::with('category')->chunkById(200, function ($products) use (&$count) {
            foreach ($products as $product) {
                $this->index($product);
                $count++;
            }
        });

        return $count;
    }
}
