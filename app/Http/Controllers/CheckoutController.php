<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\Checkout\CompleteCheckoutRequest;
use App\Models\Cart;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;
use Stripe\StripeClient;

class CheckoutController extends Controller
{
    public function index(Request $request)
    {
        $cart = Cart::with('items.product')->firstOrCreate(['user_id' => $request->user()->id]);

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index');
        }

        return Inertia::render('Checkout/Index', [
            'cart' => $cart,
            'stripeKey' => config('services.stripe.key'),
        ]);
    }

    public function createPaymentIntent(Request $request)
    {
        $cart = Cart::with('items.product')->where('user_id', $request->user()->id)->firstOrFail();

        if ($cart->items->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty.'], 422);
        }

        foreach ($cart->items as $item) {
            if ($item->product->stock < $item->quantity) {
                return response()->json(['message' => "Not enough stock for {$item->product->name}."], 422);
            }
        }

        $total = $cart->items->sum(fn ($item) => $item->quantity * $item->product->price_cents);

        $stripe = new StripeClient(config('services.stripe.secret'));
        $intent = $stripe->paymentIntents->create([
            'amount' => $total,
            'currency' => 'usd',
            'metadata' => ['user_id' => $request->user()->id],
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
        } catch (InsufficientStockException $e) {
            $stripe->refunds->create(['payment_intent' => $intent->id]);

            return redirect()->route('cart.index')
                ->withErrors(['stock' => $e->getMessage().' Your payment has been refunded.']);
        } catch (RuntimeException $e) {
            return redirect()->route('cart.index')->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()->route('orders.show', $order)->with('success', 'Order placed!');
    }
}
