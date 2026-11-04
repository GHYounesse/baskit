<?php

use App\Mail\OrderConfirmation;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeStripe;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    $this->stripe = FakeStripe::install();
    $this->user = User::factory()->create();
    $this->product = Product::factory()->create(['price_cents' => 1000, 'stock' => 5]);
    Cart::create(['user_id' => $this->user->id])->items()->create(['product_id' => $this->product->id, 'quantity' => 2]);
});

afterEach(fn () => FakeStripe::uninstall());

function completeCheckout(string $intentId, ?array $address = null)
{
    return test()->actingAs(test()->user)->post('/checkout/complete', [
        'payment_intent_id' => $intentId,
        'shipping_address' => $address ?? ['line1' => '1 Rue Test', 'city' => 'Casablanca', 'country' => 'MA'],
    ]);
}

function postWebhook(string $body, array $headers)
{
    return test()->call('POST', '/stripe/webhook', [], [], [], test()->transformHeadersToServerVars($headers), $body);
}

// ---- Creating the PaymentIntent ----

it('creates a PaymentIntent for the server-side cart total, never a client amount', function () {
    $this->actingAs($this->user)->postJson('/checkout/payment-intent', ['amount' => 1])
        ->assertOk()
        ->assertJsonStructure(['clientSecret']);

    $call = $this->stripe->callsTo('post', '/v1/payment_intents')[0];
    expect((int) $call['params']['amount'])->toBe(2000)
        ->and((int) $call['params']['metadata']['user_id'])->toBe($this->user->id);
});

it('refuses to create a PaymentIntent for an empty cart', function () {
    Cart::where('user_id', $this->user->id)->first()->items()->delete();

    $this->actingAs($this->user)->postJson('/checkout/payment-intent')->assertStatus(422);

    expect($this->stripe->calls)->toBeEmpty();
});

it('refuses to create a PaymentIntent when the cart exceeds stock', function () {
    $this->product->forceFill(['stock' => 1])->saveQuietly();

    $this->actingAs($this->user)->postJson('/checkout/payment-intent')
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'Not enough stock'));

    expect($this->stripe->calls)->toBeEmpty();
});

it('requires a login to start or complete a payment', function () {
    $this->postJson('/checkout/payment-intent')->assertUnauthorized();
    $this->postJson('/checkout/complete')->assertUnauthorized();
});

// ---- Completing the order (browser redirect flow) ----

it('turns a succeeded payment into one paid order, takes the stock and empties the cart', function () {
    $this->stripe->intent('pi_ok', $this->user->id, 2000);

    completeCheckout('pi_ok')->assertRedirect();

    $order = Order::sole();
    expect($order->status)->toBe('paid')
        ->and($order->total_cents)->toBe(2000)
        ->and($order->stripe_payment_intent_id)->toBe('pi_ok')
        ->and($order->shipping_address['city'])->toBe('Casablanca')
        ->and($this->product->fresh()->stock)->toBe(3)
        ->and(Cart::where('user_id', $this->user->id)->first()->items)->toBeEmpty();
});

it('does not trust the browser: an unpaid intent creates no order', function () {
    $this->stripe->intent('pi_unpaid', $this->user->id, 2000, 'requires_payment_method');

    completeCheckout('pi_unpaid')->assertSessionHasErrors('payment');

    expect(Order::count())->toBe(0)
        ->and($this->product->fresh()->stock)->toBe(5);
});

it("forbids completing someone else's PaymentIntent", function () {
    $other = User::factory()->create();
    $this->stripe->intent('pi_other', $other->id, 2000);

    completeCheckout('pi_other')->assertForbidden();

    expect(Order::count())->toBe(0)->and($this->product->fresh()->stock)->toBe(5);
});

it('validates the shipping address before calling Stripe', function () {
    completeCheckout('pi_ok', ['city' => 'Casablanca'])
        ->assertSessionHasErrors(['shipping_address.line1', 'shipping_address.country']);

    expect($this->stripe->calls)->toBeEmpty();
});

it('refunds the payment and creates no order when stock ran out after paying', function () {
    $this->stripe->intent('pi_late', $this->user->id, 2000);
    $this->product->forceFill(['stock' => 1])->saveQuietly();

    completeCheckout('pi_late')->assertRedirect(route('cart.index'))->assertSessionHasErrors('stock');

    $refunds = $this->stripe->callsTo('post', '/v1/refunds');
    expect(Order::count())->toBe(0)
        ->and($this->product->fresh()->stock)->toBe(1)
        ->and($refunds)->toHaveCount(1)
        ->and($refunds[0]['params']['payment_intent'])->toBe('pi_late');
});

it('refuses a paid amount that does not match the cart and creates no order', function () {
    $this->stripe->intent('pi_short', $this->user->id, 500);

    completeCheckout('pi_short')->assertRedirect(route('cart.index'))->assertSessionHasErrors('payment');

    expect(Order::count())->toBe(0)->and($this->product->fresh()->stock)->toBe(5);
});

// ---- Idempotency: redirect flow, webhook and retries ----

it('creates a single order when the customer submits the same payment twice', function () {
    $this->stripe->intent('pi_twice', $this->user->id, 2000);

    completeCheckout('pi_twice');
    completeCheckout('pi_twice');

    expect(Order::count())->toBe(1)
        ->and($this->product->fresh()->stock)->toBe(3)
        ->and(StockMovement::where('reason', StockMovement::SALE)->count())->toBe(1);
    Mail::assertQueued(OrderConfirmation::class, 1);
});

it('creates a single order when the webhook and the redirect both report the payment', function () {
    $intent = $this->stripe->intent('pi_race', $this->user->id, 2000);
    [$body, $headers] = $this->stripe->signedEvent('payment_intent.succeeded', $intent);

    postWebhook($body, $headers)->assertOk();
    completeCheckout('pi_race');

    expect(Order::count())->toBe(1)
        ->and($this->product->fresh()->stock)->toBe(3);
    Mail::assertQueued(OrderConfirmation::class, 1);
});

it('ignores Stripe redelivering the same webhook event', function () {
    $intent = $this->stripe->intent('pi_redeliver', $this->user->id, 2000);
    [$body, $headers] = $this->stripe->signedEvent('payment_intent.succeeded', $intent);

    foreach (range(1, 3) as $_) {
        postWebhook($body, $headers)->assertOk();
    }

    expect(Order::count())->toBe(1)
        ->and($this->product->fresh()->stock)->toBe(3)
        ->and($this->stripe->callsTo('post', '/v1/refunds'))->toBeEmpty();
});

// ---- Webhook security and behaviour ----

it('rejects a webhook signed with the wrong secret', function () {
    $intent = $this->stripe->intent('pi_forged', $this->user->id, 2000);
    [$body, $headers] = $this->stripe->signedEvent('payment_intent.succeeded', $intent, 'whsec_attacker');

    postWebhook($body, $headers)->assertStatus(400);

    expect(Order::count())->toBe(0);
});

it('rejects a webhook whose body was tampered with', function () {
    $intent = $this->stripe->intent('pi_tamper', $this->user->id, 2000);
    [$body, $headers] = $this->stripe->signedEvent('payment_intent.succeeded', $intent);

    postWebhook(str_replace('2000', '1', $body), $headers)->assertStatus(400);

    expect(Order::count())->toBe(0);
});

it('refunds from the webhook when stock ran out, without creating an order', function () {
    $intent = $this->stripe->intent('pi_hook_late', $this->user->id, 2000);
    $this->product->forceFill(['stock' => 1])->saveQuietly();
    [$body, $headers] = $this->stripe->signedEvent('payment_intent.succeeded', $intent);

    postWebhook($body, $headers)->assertOk();

    expect(Order::count())->toBe(0)
        ->and($this->stripe->callsTo('post', '/v1/refunds'))->toHaveCount(1);
});

it('acknowledges a webhook whose cart is already gone, without refunding', function () {
    Cart::where('user_id', $this->user->id)->first()->items()->delete();
    $intent = $this->stripe->intent('pi_gone', $this->user->id, 2000);
    [$body, $headers] = $this->stripe->signedEvent('payment_intent.succeeded', $intent);

    postWebhook($body, $headers)->assertOk();

    expect(Order::count())->toBe(0)->and($this->stripe->callsTo('post', '/v1/refunds'))->toBeEmpty();
});

it('marks the order cancelled when Stripe reports a full refund, once', function () {
    $this->stripe->intent('pi_refunded', $this->user->id, 2000);
    completeCheckout('pi_refunded');

    [$body, $headers] = $this->stripe->signedEvent('charge.refunded', [
        'id' => 'ch_1', 'object' => 'charge', 'payment_intent' => 'pi_refunded', 'refunded' => true,
    ]);

    postWebhook($body, $headers)->assertOk();
    postWebhook($body, $headers)->assertOk();

    expect(Order::sole()->status)->toBe('cancelled')
        ->and(Order::sole()->events()->where('type', 'cancelled')->count())->toBe(1);
});

it('ignores a partial refund', function () {
    $this->stripe->intent('pi_partial', $this->user->id, 2000);
    completeCheckout('pi_partial');

    [$body, $headers] = $this->stripe->signedEvent('charge.refunded', [
        'id' => 'ch_2', 'object' => 'charge', 'payment_intent' => 'pi_partial', 'refunded' => false,
    ]);
    postWebhook($body, $headers)->assertOk();

    expect(Order::sole()->status)->toBe('paid');
});
