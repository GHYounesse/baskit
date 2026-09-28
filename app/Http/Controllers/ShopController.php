<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
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
    ];

    public function index(Request $request)
    {
        $sort = $request->input('sort');
        [$column, $direction] = self::SORTS[$sort] ?? self::SORTS['newest'];

        $products = Product::query()
            ->where('is_active', true)
            ->when($request->category, fn ($q, $slug) => $q->whereHas('category', fn ($q) => $q->where('slug', $slug)))
            ->when($request->search, fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy($column, $direction)
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Shop/Index', [
            'products' => $products,
            'categories' => Category::all(),
            'filters' => $request->only(['category', 'search', 'sort']),
        ]);
    }

    public function show(Product $product)
    {
        return Inertia::render('Shop/Show', ['product' => $product->load('category')]);
    }
}
