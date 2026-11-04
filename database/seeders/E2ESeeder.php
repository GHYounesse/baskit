<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Fixed fixtures for the Playwright end-to-end tests, on top of DatabaseSeeder
 * (which provides admin@example.com / customer@example.com and the demo coupons).
 * The specs rely on these exact names, prices and stock levels.
 */
class E2ESeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::firstOrCreate(['slug' => 'books'], ['name' => 'Books']);

        Product::updateOrCreate(['slug' => 'e2e-desk-lamp'], [
            'category_id' => $category->id,
            'name' => 'E2E Desk Lamp',
            'description' => 'A lamp that only exists for the end-to-end tests.',
            'price_cents' => 2000,
            'stock' => 5,
            'is_active' => true,
        ]);
    }
}
