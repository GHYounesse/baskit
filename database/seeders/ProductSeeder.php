<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Curated per-category catalog: product name plus a realistic price
     * range in cents. Keeps the demo shop looking like a real store instead
     * of random word-salad names at a flat, category-blind price.
     */
    private const CATALOG = [
        'electronics' => [
            'price_range' => [1500, 29900],
            'products' => [
                'Wireless Headphones', 'Bluetooth Speaker', 'Smartwatch', 'Laptop Stand',
                'Mechanical Keyboard', 'USB-C Hub', 'Wireless Mouse', 'Portable Charger',
                'Noise Cancelling Earbuds', 'HD Webcam',
            ],
        ],
        'clothing' => [
            'price_range' => [1000, 12000],
            'products' => [
                'Cotton T-Shirt', 'Denim Jacket', 'Running Shoes', 'Wool Beanie',
                'Canvas Tote Bag', 'Slim Fit Jeans', 'Hooded Sweatshirt', 'Leather Belt',
                'Ankle Socks (3-Pack)', 'Baseball Cap',
            ],
        ],
        'home-kitchen' => [
            'price_range' => [800, 15000],
            'products' => [
                'Ceramic Coffee Mug', 'Stainless Steel Water Bottle', 'Cast Iron Skillet', 'Table Lamp',
                'Throw Pillow Cover', 'Bamboo Cutting Board', 'Scented Candle', 'Indoor Plant Pot',
                'Kitchen Knife Set', 'Cotton Bath Towel',
            ],
        ],
        'books' => [
            'price_range' => [600, 3500],
            'products' => [
                'The Art of Focus', 'Modern Web Design', 'A History of Everything', 'The Midnight Garden',
                'Cooking for Beginners', 'Atomic Habits Explained', 'The Silent Ocean', 'Starting a Business 101',
                'Poems for Quiet Mornings', 'The Last Expedition',
            ],
        ],
        'toys' => [
            'price_range' => [800, 6000],
            'products' => [
                'Wooden Building Blocks', 'Plush Teddy Bear', 'Remote Control Car', 'Puzzle Set (500 pcs)',
                'Classic Board Game', 'Rubber Duck Bath Toy', 'Building Bricks Set', 'Toy Dinosaur Figure',
                'Kids Art Easel', 'Spinning Top Set',
            ],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::CATALOG as $slug => $catalog) {
            $category = Category::where('slug', $slug)->first();

            foreach ($catalog['products'] as $index => $name) {
                Product::create([
                    'category_id' => $category?->id,
                    'name' => $name,
                    'slug' => Str::slug($name).'-'.Str::random(6),
                    'description' => "A great {$name} for your everyday basket.",
                    'price_cents' => fake()->numberBetween(...$catalog['price_range']),
                    'stock' => fake()->numberBetween(0, 100),
                    'is_active' => true,
                ]);
            }
        }
    }
}
