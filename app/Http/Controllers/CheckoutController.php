<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Order;
use Inertia\Inertia;
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
        $total = $cart->items->sum(fn ($item) => $item->quantity * $item->product->price_cents);

        $stripe = new StripeClient(config('services.stripe.secret'));
        $intent = $stripe->paymentIntents->create([
            'amount' => $total,
            'currency' => 'usd',
            'metadata' => ['user_id' => $request->user()->id],
        ]);

        return response()->json(['clientSecret' => $intent->client_secret]);
    }

    public function complete(Request $request)
    {
        $data = $request->validate([
            'payment_intent_id' => 'required|string',
            'shipping_address' => 'required|array',
        ]);

        $cart = Cart::with('items.product')->where('user_id', $request->user()->id)->firstOrFail();

        $order = Order::create([
            'user_id' => $request->user()->id,
            'status' => 'paid',
            'total_cents' => $cart->items->sum(fn ($i) => $i->quantity * $i->product->price_cents),
            'stripe_payment_intent_id' => $data['payment_intent_id'],
            'shipping_address' => $data['shipping_address'],
        ]);

        foreach ($cart->items as $item) {
            $order->items()->create([
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'unit_price_cents' => $item->product->price_cents,
                'quantity' => $item->quantity,
            ]);
            $item->product->decrement('stock', $item->quantity);
        }

        $cart->items()->delete();

        return redirect()->route('orders.show', $order)->with('success', 'Order placed!');
    }
}
