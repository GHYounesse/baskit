<?php

namespace App\Services;

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
     * Move the session's guest cart into the user's cart, summing quantities.
     */
    public function mergeGuestCart(User $user, ?string $token): void
    {
        if (! $token || ! ($guest = Cart::where('token', $token)->whereNull('user_id')->first())) {
            return;
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        foreach ($guest->items as $item) {
            $existing = $cart->items()->where('product_id', $item->product_id)->first();

            $existing
                ? $existing->increment('quantity', $item->quantity)
                : $cart->items()->create(['product_id' => $item->product_id, 'quantity' => $item->quantity]);
        }

        $guest->delete();
    }
}
