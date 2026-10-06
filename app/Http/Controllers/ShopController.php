<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Services\Search\StoreSearch;
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

    public function index(Request $request, StoreSearch $search)
    {
        $term = trim((string) $request->input('search'));
        $ids = $term !== '' ? $search->ids($term) : null;

        // Searching sorts by best match unless a sort was chosen.
        $sort = $request->input('sort') ?: ($ids !== null ? 'relevance' : null);
        $byRelevance = $sort === 'relevance' && $ids !== null;
        [$column, $direction] = self::SORTS[$sort] ?? self::SORTS['newest'];

        $products = Product::query()
            ->with('category')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->when($request->category, fn ($q, $slug) => $q->whereHas('category', fn ($q) => $q->where('slug', $slug)))
            // Words must match in the name, category or description; best match first.
            ->when($ids !== null, fn ($q) => $q->whereIn('products.id', $ids ?: [0]))
            ->when(
                $byRelevance && $ids !== [],
                fn ($q) => $q->orderByRaw('CASE products.id '.implode(' ', array_map(fn ($id, $i) => "WHEN {$id} THEN {$i}", $ids, array_keys($ids))).' END'),
                fn ($q) => $q->orderBy($column, $direction),
            )
            // Among equal averages, the one backed by more reviews ranks higher.
            ->when($sort === 'rating', fn ($q) => $q->orderByDesc('reviews_count'))
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Shop/Index', [
            'products' => $products,
            'categories' => Category::all(),
            'filters' => [
                ...$request->only(['category', 'search']),
                'sort' => $sort,
            ],
            // Offered when a search finds nothing: "Did you mean ...?"
            'correction' => $ids === [] ? $search->correction($term) : null,
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
            'product' => $product->load('category', 'variants'),
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
