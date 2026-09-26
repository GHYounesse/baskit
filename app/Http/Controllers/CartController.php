<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CartController extends Controller
{
    public function __construct(private CartService $carts) {}

    public function index(Request $request)
    {
        $cart = $this->currentCart($request);

        return Inertia::render('Cart/Index', [
            'cart' => $cart->load('items.product'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $this->currentCart($request);

        $item = $cart->items()->where('product_id', $data['product_id'])->first();
        if ($item) {
            $item->increment('quantity', $data['quantity']);
        } else {
            $cart->items()->create($data);
        }

        return back()->with('success', 'Added to cart.');
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $this->authorizeItem($request, $cartItem);
        $request->validate(['quantity' => 'required|integer|min:1']);
        $cartItem->update(['quantity' => $request->quantity]);

        return back();
    }

    public function destroy(Request $request, CartItem $cartItem)
    {
        $this->authorizeItem($request, $cartItem);
        $cartItem->delete();

        return back();
    }

    private function authorizeItem(Request $request, CartItem $cartItem): void
    {
        abort_unless($cartItem->cart_id === $this->carts->current($request)->id, 403);
    }

    private function currentCart(Request $request): Cart
    {
        return $this->carts->current($request);
    }
}
