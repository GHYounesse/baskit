<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Full-text index for product search, using SQLite's FTS5. Other databases
 * skip it: they use the portable LIKE-based engine instead (see
 * App\Providers\AppServiceProvider and App\Services\Search).
 *
 * - remove_diacritics: "cafe" finds "Café".
 * - prefix='2 3': makes typing-as-you-search ("head" -> "headphones") fast.
 * - product_id is stored but not indexed; it only links a row back to a product.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE VIRTUAL TABLE product_search USING fts5(
                product_id UNINDEXED,
                name,
                category,
                description,
                tokenize = 'unicode61 remove_diacritics 2',
                prefix = '2 3'
            )
        SQL);

        // Index the products that already exist.
        DB::statement(<<<'SQL'
            INSERT INTO product_search (product_id, name, category, description)
            SELECT products.id, products.name, COALESCE(categories.name, ''), COALESCE(products.description, '')
            FROM products
            LEFT JOIN categories ON categories.id = products.category_id
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TABLE IF EXISTS product_search');
        }
    }
};
