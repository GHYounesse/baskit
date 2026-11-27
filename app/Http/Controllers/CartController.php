<?php

namespace App\Http\Controllers;

use App\Exceptions\CouponException;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\ApplyCouponRequest;
use App\Http\Requests\Cart\UpdateCartItemQuantityRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CartController extends Controller
{
    public function __construct(private CartService $carts) {}

    public function index(Request $request)
    {
        $cart = $this->currentCart($request);

        $cart->load('items.product', 'items.variant');

        return Inertia::render('Cart/Index', [
            'cart' => $cart,
            'pricing' => $this->carts->pricing($cart, $request->user()),
            // An empty cart suggests where to go next; a full one does not need the query.
            'suggestions' => $cart->items->isEmpty() ? $this->popularProducts() : [],
        ]);
    }

    /**
     * The most-reviewed products that can be bought right now.
     */
    private function popularProducts()
    {
        return Product::query()
            ->with('category')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->orderByDesc('reviews_count')
            ->latest()
            ->limit(4)
            ->get();
    }

    public function store(AddCartItemRequest $request)
    {
        $data = $request->validated();

        $cart = $this->currentCart($request);

        // The same product in a different size or colour is a separate line.
        $variantId = $data['variant_id'] ?? null;

        $item = $cart->items()
            ->where('product_id', $data['product_id'])
            ->where('product_variant_id', $variantId)
            ->first();

        if ($item) {
            $item->increment('quantity', $data['quantity']);
        } else {
            $cart->items()->create([
                'product_id' => $data['product_id'],
                'product_variant_id' => $variantId,
                'quantity' => $data['quantity'],
            ]);
        }

        return back()->with('success', 'Added to cart.');
    }

    public function applyCoupon(ApplyCouponRequest $request)
    {
        $cart = $this->currentCart($request);
        $pricing = $this->carts->pricing($cart->setRelation('coupon', null));

        $coupon = Coupon::findByCode($request->validated('code'));

        if (! $coupon) {
            throw ValidationException::withMessages(['code' => "That code isn't valid."]);
        }

        if ($pricing['subtotal_cents'] === 0) {
            throw ValidationException::withMessages(['code' => 'Add something to your cart first.']);
        }

        try {
            $coupon->validateFor($pricing['subtotal_cents'], $request->user());
        } catch (CouponException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        $cart->update(['coupon_id' => $coupon->id]);

        return back()->with('success', "Code {$coupon->code} applied.");
    }

    public function removeCoupon(Request $request)
    {
        $this->currentCart($request)->update(['coupon_id' => null]);

        return back();
    }

    public function update(UpdateCartItemQuantityRequest $request, CartItem $cartItem)
    {
        $this->authorizeItem($request, $cartItem);
        $cartItem->update($request->validated());

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
