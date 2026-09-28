<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Mail\OrderConfirmation;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Stripe\PaymentIntent;

class OrderService
{
    /**
     * Turn a succeeded PaymentIntent into an order. Idempotent: calling it
     * again for the same intent returns the existing order.
     */
    public function createFromPaymentIntent(PaymentIntent $intent, ?array $shippingAddress = null): Order
    {
        if ($intent->status !== 'succeeded') {
            throw new RuntimeException('Payment has not succeeded.');
        }

        $userId = (int) ($intent->metadata['user_id'] ?? 0);

        $order = DB::transaction(function () use ($intent, $userId, $shippingAddress) {
            if ($existing = Order::where('stripe_payment_intent_id', $intent->id)->first()) {
                return $existing;
            }

            $cart = Cart::with('items')->where('user_id', $userId)->first();

            if (! $cart || $cart->items->isEmpty()) {
                throw new RuntimeException('Cart is empty.');
            }

            $products = Product::whereIn('id', $cart->items->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $total = 0;
            foreach ($cart->items as $item) {
                $product = $products[$item->product_id];

                if ($product->stock < $item->quantity) {
                    throw new InsufficientStockException($product->name);
                }

                $total += $item->quantity * $product->price_cents;
            }

            if ($total !== (int) $intent->amount_received) {
                throw new RuntimeException('Paid amount does not match cart total.');
            }

            $order = Order::create([
                'user_id' => $userId,
                'status' => 'paid',
                'total_cents' => $total,
                'stripe_payment_intent_id' => $intent->id,
                'shipping_address' => $shippingAddress,
            ]);

            foreach ($cart->items as $item) {
                $product = $products[$item->product_id];

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price_cents' => $product->price_cents,
                    'quantity' => $item->quantity,
                ]);
                $product->decrement('stock', $item->quantity);
            }

            $cart->items()->delete();

            return $order;
        });

        // Both the redirect flow and the webhook can call this for the same
        // intent; only the one that actually created the order sends mail.
        if ($order->wasRecentlyCreated) {
            Mail::to($order->user->email)->queue(new OrderConfirmation($order));
        }

        return $order;
    }
}
