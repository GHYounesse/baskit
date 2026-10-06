<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Wishlist/Index', [
            'products' => $request->user()->wishlist()
                ->with('category')
                ->withAvg('reviews', 'rating')
                ->withCount('reviews', 'variants')
                ->where('is_active', true)
                ->orderByPivot('created_at', 'desc')
                ->get(),
        ]);
    }

    /**
     * Idempotent: saving a product that's already saved is a no-op.
     */
    public function store(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);

        $request->user()->wishlist()->syncWithoutDetaching([$product->id]);

        return back();
    }

    public function destroy(Request $request, Product $product)
    {
        $request->user()->wishlist()->detach($product->id);

        return back();
    }
}
