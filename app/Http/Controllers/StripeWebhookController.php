<?php

namespace App\Http\Controllers;

use App\Exceptions\CouponException;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Services\OrderLifecycleService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, OrderService $orders, OrderLifecycleService $lifecycle)
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret'),
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response('Invalid webhook.', 400);
        }

        if ($event->type === 'payment_intent.succeeded') {
            try {
                $orders->createFromPaymentIntent($event->data->object);
            } catch (InsufficientStockException|CouponException $e) {
                (new StripeClient(config('services.stripe.secret')))->refunds->create([
                    'payment_intent' => $event->data->object->id,
                ]);
                Log::warning('Refunded payment: '.$e->getMessage(), ['intent' => $event->data->object->id]);
            } catch (\RuntimeException $e) {
                // e.g. cart already consumed by the redirect flow; nothing to do
                Log::info('Webhook order skipped: '.$e->getMessage());
            }
        }

        // A full refund made outside the app (the Stripe dashboard) should show up
        // on the order too. Partial refunds are not modelled, so they are ignored.
        if ($event->type === 'charge.refunded' && $event->data->object->refunded) {
            $order = Order::where('stripe_payment_intent_id', $event->data->object->payment_intent)->first();

            if ($order) {
                $lifecycle->syncRefundFromProvider($order);
            }
        }

        return response('ok');
    }
}
