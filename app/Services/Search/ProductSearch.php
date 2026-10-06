<?php

namespace App\Services\Search;

use App\Models\Product;

/**
 * A product search engine. Implementations answer "which products match this,
 * best first?" and keep whatever index they use in step with the catalogue.
 * Swapping in another engine (Meilisearch through Laravel Scout, say) means
 * writing one more class with these four methods.
 */
interface ProductSearch
{
    /**
     * IDs of active products matching every word of the query, best match first.
     *
     * @return list<int>
     */
    public function ids(string $query, int $limit = 200): array;

    /**
     * Add or refresh a product in the index.
     */
    public function index(Product $product): void;

    public function remove(int $productId): void;

    /**
     * Rebuild the whole index from the database. Returns how many products were indexed.
     */
    public function reindex(): int;
}
