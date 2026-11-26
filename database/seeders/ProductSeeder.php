<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

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
                    'image_path' => $this->storeImage($slug),
                ]);
            }
        }
    }

    /**
     * Copies database/seeders/images/{slug}.(jpg|jpeg|png|webp) into the public
     * disk and returns its path, or null when there is no such file.
     */
    private function storeImage(string $slug): ?string
    {
        foreach (self::IMAGE_EXTENSIONS as $extension) {
            $source = database_path("seeders/images/{$slug}.{$extension}");

            if (is_file($source)) {
                $path = "products/{$slug}.{$extension}";
                Storage::disk('public')->put($path, file_get_contents($source));

                return $path;
            }
        }

        return null;
    }
}
