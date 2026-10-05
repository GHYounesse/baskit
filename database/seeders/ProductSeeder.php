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
     * Stable, verified Unsplash photos per category. Cycled per product
     * within a category rather than matched one-to-one, since there are
     * more products than distinct free photos worth curating by hand.
     */
    private const IMAGES = [
        'electronics' => [
            '1505740420928-5e560c06d30e', '1519389950473-47ba0277781c', '1526170375885-4d8ecf77b99f',
            '1546435770-a3e426bf472b', '1560472354-b33ff0c44a43',
        ],
        'clothing' => [
            '1542291026-7eec264c27ff', '1549298916-b41d501d3772', '1551028719-00167b16eac5',
            '1521369909029-2afed882baee', '1445205170230-053b83016050', '1489987707025-afc232f7ea0f',
        ],
        'home-kitchen' => [
            '1556911220-e15b29be8c8f', '1493663284031-b7e3aefcae8e', '1556909114-f6e7ad7d3136',
            '1556912167-f556f1f39fdf', '1541123437800-1bb1317badc2', '1556228453-efd6c1ff04f6',
        ],
        'books' => [
            '1512820790803-83ca734da794', '1544716278-ca5e3f4abd8c', '1481627834876-b7833e8f5570',
            '1495446815901-a7297e633e8d',
        ],
        'toys' => [
            '1596461404969-9ae70f2830c1', '1558877385-81a1c7e67d72', '1560859251-d563a49c5e4a',
            '1587654780291-39c9404d746b', '1558060370-d644479cb6f7',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::CATALOG as $slug => $catalog) {
            $category = Category::where('slug', $slug)->first();
            $images = self::IMAGES[$slug];

            foreach ($catalog['products'] as $index => $name) {
                $photoId = $images[$index % count($images)];

                Product::create([
                    'category_id' => $category?->id,
                    'name' => $name,
                    'slug' => Str::slug($name).'-'.Str::random(6),
                    'description' => "A great {$name} for your everyday basket.",
                    'price_cents' => fake()->numberBetween(...$catalog['price_range']),
                    'stock' => fake()->numberBetween(0, 100),
                    'is_active' => true,
                    'image_path' => "https://images.unsplash.com/photo-{$photoId}?w=600&h=600&fit=crop",
                ]);
            }
        }
    }
}
