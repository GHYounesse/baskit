<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->where('is_active', true)
            ->when($request->category, fn ($q, $slug) => $q->whereHas('category', fn ($q) => $q->where('slug', $slug)))
            ->when($request->search, fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Shop/Index', [
            'products' => $products,
            'categories' => Category::all(),
            'filters' => $request->only(['category', 'search']),
        ]);
    }

    public function show(Product $product)
    {
        return Inertia::render('Shop/Show', ['product' => $product->load('category')]);
    }
}
