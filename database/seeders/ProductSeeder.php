<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Seeds the curated catalogue from data/catalogue.php. Needs no Faker, so
     * it runs in the production image. Safe to run repeatedly: a product is
     * matched by its slug and an existing one is left alone, so real stock and
     * edits are never overwritten.
     */
    public function run(): void
    {
        foreach (require database_path('seeders/data/catalogue.php') as $categorySlug => $catalogue) {
            $category = Category::where('slug', $categorySlug)->first();

            foreach ($catalogue['products'] as $item) {
                $slug = Str::slug($item['name']);

                Product::firstOrCreate(['slug' => $slug], [
                    'category_id' => $category?->id,
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'price_cents' => $item['price_cents'],
                    'stock' => $item['stock'],
                    'is_active' => true,
                ]);
            }
        }
    }
}
