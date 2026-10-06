<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentRefundException;

interface PaymentRefunder
{
    /**
     * Refund a payment in full.
     *
     * The idempotency key makes retries safe: asking twice with the same key
     * refunds once. Returns the provider's refund id, or null if the payment
     * had already been fully refunded.
     *
     * @throws PaymentRefundException
     */
    public function refund(string $paymentIntentId, string $idempotencyKey): ?string;
}
