<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, OrderService $orders)
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
            } catch (InsufficientStockException $e) {
                (new StripeClient(config('services.stripe.secret')))->refunds->create([
                    'payment_intent' => $event->data->object->id,
                ]);
                Log::warning('Refunded payment due to stock shortage', ['intent' => $event->data->object->id]);
            } catch (\RuntimeException $e) {
                // e.g. cart already consumed by the redirect flow; nothing to do
                Log::info('Webhook order skipped: '.$e->getMessage());
            }
        }

        return response('ok');
    }
}
