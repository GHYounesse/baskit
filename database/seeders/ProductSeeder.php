<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::factory()
            ->count(40)
            ->create()
            ->each(function (Product $product) {
                // Picsum's seeded endpoint returns the same stock photo for a
                // given seed every time, so demo products keep a stable image
                // across reseeds without storing any files in the repo.
                $product->update(['image_path' => "https://picsum.photos/seed/{$product->slug}/600/600"]);
            });
    }
}
