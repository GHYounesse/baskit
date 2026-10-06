<?php

namespace App\Services;

use App\Exceptions\CouponException;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartService
{
    private const SESSION_KEY = 'cart_token';

    /**
     * The cart for the logged-in user, or for the guest's session.
     */
    public function current(Request $request): Cart
    {
        if ($user = $request->user()) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }

        $token = $request->session()->get(self::SESSION_KEY);

        if (! $token) {
            $token = Str::random(40);
            $request->session()->put(self::SESSION_KEY, $token);
        }

        return Cart::firstOrCreate(['token' => $token]);
    }

    /**
     * Number of items in the current cart, without creating a cart.
     */
    public function count(Request $request): int
    {
        $cart = $request->user()
            ? Cart::where('user_id', $request->user()->id)->first()
            : Cart::where('token', $request->session()->get(self::SESSION_KEY))->first();

        return (int) ($cart?->items()->sum('quantity') ?? 0);
    }

    /**
     * What the cart costs, with the applied coupon (if any) taken off.
     * A coupon that no longer qualifies is reported in `coupon_error` and
     * discounts nothing, so callers decide whether that blocks payment.
     *
     * @return array{subtotal_cents: int, discount_cents: int, total_cents: int, coupon: ?array{code: string, label: string}, coupon_error: ?string}
     */
    public function pricing(Cart $cart, ?User $user = null): array
    {
        $cart->loadMissing('items.product', 'coupon');

        $subtotal = (int) $cart->items->sum(fn ($item) => $item->quantity * $item->product->price_cents);
        $discount = 0;
        $couponError = null;

        if ($cart->coupon) {
            try {
                $cart->coupon->validateFor($subtotal, $user);
                $discount = $cart->coupon->discountFor($subtotal);
            } catch (CouponException $e) {
                $couponError = $e->getMessage();
            }
        }

        return [
            'subtotal_cents' => $subtotal,
            'discount_cents' => $discount,
            'total_cents' => $subtotal - $discount,
            'coupon' => $cart->coupon
                ? ['code' => $cart->coupon->code, 'label' => $cart->coupon->label]
                : null,
            'coupon_error' => $couponError,
        ];
    }

    /**
     * Move the session's guest cart into the user's cart, summing quantities.
     */
    public function mergeGuestCart(User $user, ?string $token): void
    {
        if (! $token || ! ($guest = Cart::where('token', $token)->whereNull('user_id')->first())) {
            return;
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        foreach ($guest->items as $item) {
            $existing = $cart->items()
                ->where('product_id', $item->product_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->first();

            $existing
                ? $existing->increment('quantity', $item->quantity)
                : $cart->items()->create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity,
                ]);
        }

        if ($guest->coupon_id && ! $cart->coupon_id) {
            $cart->update(['coupon_id' => $guest->coupon_id]);
        }

        $guest->delete();
    }
}
