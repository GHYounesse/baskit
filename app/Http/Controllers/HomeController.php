<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\StoreSeo;
use Inertia\Inertia;

class HomeController extends Controller
{
    /** How many photos the hero shows, and how many thumbnails each category row shows. */
    private const HERO_PRODUCTS = 4;

    private const THUMBS_PER_CATEGORY = 3;

    private const JUST_IN = 4;

    public function index()
    {
        $categories = Category::query()
            ->withCount(['products as products_count' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category) => $category->products_count > 0)
            ->values()
            ->map(function (Category $category) {
                $category->setAttribute('thumbs', $category->products()
                    ->where('is_active', true)
                    ->whereNotNull('image_path')
                    ->latest()
                    ->limit(self::THUMBS_PER_CATEGORY)
                    ->get(['id', 'name', 'slug', 'image_path', 'category_id'])
                    ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'image_url' => $p->image_url]));

                return $category;
            });

        // One in-stock product with a photo from each of the first few categories, so the
        // hero shows variety instead of four things from the same aisle.
        $hero = $categories
            ->map(fn (Category $category) => $category->products()
                ->with('category')
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->whereNotNull('image_path')
                ->latest()
                ->first())
            ->filter()
            ->take(self::HERO_PRODUCTS)
            ->values();

        $justIn = Product::query()
            ->with('category')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->latest()
            ->limit(self::JUST_IN)
            ->get();

        return Inertia::render('Home', [
            'seo' => StoreSeo::home()->toArray(),
            'hero' => $hero->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price_cents' => $p->price_cents,
                'image_url' => $p->image_url,
                'category' => $p->category?->name,
            ]),
            'categories' => $categories->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'products_count' => $c->products_count,
                'thumbs' => $c->thumbs,
            ]),
            'justIn' => $justIn,
        ]);
    }
}
