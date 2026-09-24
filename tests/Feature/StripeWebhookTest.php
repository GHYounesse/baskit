<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_invalid_signature(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $this->postJson('/stripe/webhook', ['type' => 'payment_intent.succeeded'], [
            'Stripe-Signature' => 't=1,v1=bad',
        ])->assertStatus(400);
    }
}
