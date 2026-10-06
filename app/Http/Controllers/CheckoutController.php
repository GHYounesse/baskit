<?php

namespace App\Http\Controllers;

use App\Exceptions\CouponException;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\Checkout\CompleteCheckoutRequest;
use App\Models\Cart;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;
use Stripe\StripeClient;

class CheckoutController extends Controller
{
    public function __construct(private CartService $carts) {}

    public function index(Request $request)
    {
        $cart = Cart::with('items.product', 'items.variant')->firstOrCreate(['user_id' => $request->user()->id]);

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index');
        }

        return Inertia::render('Checkout/Index', [
            'cart' => $cart,
            'pricing' => $this->carts->pricing($cart, $request->user()),
            'stripeKey' => config('services.stripe.key'),
        ]);
    }

    public function createPaymentIntent(Request $request)
    {
        $cart = Cart::with('items.product', 'items.variant')->where('user_id', $request->user()->id)->firstOrFail();

        if ($cart->items->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty.'], 422);
        }

        foreach ($cart->items as $item) {
            $available = $item->variant ? $item->variant->stock : $item->product->stock;

            if ($available < $item->quantity) {
                $name = $item->variant ? "{$item->product->name} ({$item->variant->label})" : $item->product->name;

                return response()->json(['message' => "Not enough stock for {$name}."], 422);
            }
        }

        $pricing = $this->carts->pricing($cart, $request->user());

        if ($pricing['coupon_error']) {
            return response()->json([
                'message' => "Code {$pricing['coupon']['code']}: {$pricing['coupon_error']} Remove it from your cart to continue.",
            ], 422);
        }

        $stripe = new StripeClient(config('services.stripe.secret'));
        $intent = $stripe->paymentIntents->create([
            'amount' => $pricing['total_cents'],
            'currency' => 'usd',
            'metadata' => array_filter([
                'user_id' => $request->user()->id,
                'coupon_id' => $cart->coupon_id,
            ]),
        ]);

        return response()->json(['clientSecret' => $intent->client_secret]);
    }

    public function complete(CompleteCheckoutRequest $request, OrderService $orders)
    {
        $data = $request->validated();

        // Never trust the client: fetch the intent from Stripe and verify it.
        $stripe = new StripeClient(config('services.stripe.secret'));
        $intent = $stripe->paymentIntents->retrieve($data['payment_intent_id']);

        if ((int) ($intent->metadata['user_id'] ?? 0) !== $request->user()->id) {
            abort(403);
        }

        if ($intent->status !== 'succeeded') {
            return back()->withErrors(['payment' => 'Payment was not completed.']);
        }

        try {
            $order = $orders->createFromPaymentIntent($intent, $data['shipping_address']);
        } catch (InsufficientStockException|CouponException $e) {
            $stripe->refunds->create(['payment_intent' => $intent->id]);

            return redirect()->route('cart.index')
                ->withErrors(['stock' => $e->getMessage().' Your payment has been refunded.']);
        } catch (RuntimeException $e) {
            return redirect()->route('cart.index')->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()->route('orders.show', $order)->with('success', 'Order placed!');
    }
}
