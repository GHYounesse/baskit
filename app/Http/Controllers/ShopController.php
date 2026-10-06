<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShopController extends Controller
{
    /**
     * Allowed values for the `sort` query param, mapped to a column/direction.
     * Whitelisted so the request can't sort by an arbitrary column.
     */
    private const SORTS = [
        'newest' => ['created_at', 'desc'],
        'price_asc' => ['price_cents', 'asc'],
        'price_desc' => ['price_cents', 'desc'],
        'name' => ['name', 'asc'],
        'rating' => ['reviews_avg_rating', 'desc'],
    ];

    public function index(Request $request)
    {
        $sort = $request->input('sort');
        [$column, $direction] = self::SORTS[$sort] ?? self::SORTS['newest'];

        $products = Product::query()
            ->with('category')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->when($request->category, fn ($q, $slug) => $q->whereHas('category', fn ($q) => $q->where('slug', $slug)))
            ->when($request->search, fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy($column, $direction)
            // Among equal averages, the one backed by more reviews ranks higher.
            ->when($sort === 'rating', fn ($q) => $q->orderByDesc('reviews_count'))
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Shop/Index', [
            'products' => $products,
            'categories' => Category::all(),
            'filters' => $request->only(['category', 'search', 'sort']),
        ]);
    }

    public function show(Request $request, Product $product)
    {
        abort_unless($product->is_active || $request->user()?->is_admin, 404);

        $related = Product::query()
            ->with('category')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->latest()
            ->limit(4)
            ->get();

        $user = $request->user();
        $myReview = $user ? $product->reviews()->with('user')->where('user_id', $user->id)->first() : null;

        return Inertia::render('Shop/Show', [
            'product' => $product->load('category'),
            'related' => $related,
            'ratings' => $product->ratingSummary(),
            'reviews' => $product->reviews()
                ->with('user')
                ->when($myReview, fn ($q) => $q->whereKeyNot($myReview->id))
                ->latest()
                ->paginate(8, ['*'], 'reviews_page')
                ->withQueryString(),
            'myReview' => $myReview,
            'canReview' => $user && ! $myReview && $user->can('create', [Review::class, $product]),
        ]);
    }
}
