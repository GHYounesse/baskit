<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentRefundException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeRefunder implements PaymentRefunder
{
    public function __construct(private StripeClient $stripe) {}

    public function refund(string $paymentIntentId, string $idempotencyKey): ?string
    {
        try {
            $refund = $this->stripe->refunds->create(
                ['payment_intent' => $paymentIntentId],
                ['idempotency_key' => $idempotencyKey],
            );
        } catch (ApiErrorException $e) {
            // Refunded already, e.g. from the Stripe dashboard: nothing left to do.
            if ($e->getStripeCode() === 'charge_already_refunded') {
                return null;
            }

            throw new PaymentRefundException('Stripe could not process the refund: '.$e->getMessage(), 0, $e);
        }

        return $refund->id;
    }
}
