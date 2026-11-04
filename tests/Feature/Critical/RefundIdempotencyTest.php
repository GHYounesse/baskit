<?php

use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderLifecycleService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeStripe;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    $this->stripe = FakeStripe::install();
    $this->product = Product::factory()->create(['price_cents' => 1000, 'stock' => 3]);
    $this->order = Order::create([
        'user_id' => User::factory()->create()->id,
        'status' => 'paid',
        'total_cents' => 2000,
        'stripe_payment_intent_id' => 'pi_paid',
    ]);
    $this->order->items()->create([
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'unit_price_cents' => 1000,
        'quantity' => 2,
    ]);
});

afterEach(fn () => FakeStripe::uninstall());

it('sends the refund to Stripe with a stable per-order idempotency key', function () {
    app(OrderLifecycleService::class)->refund($this->order);

    $call = $this->stripe->callsTo('post', '/v1/refunds')[0];
    expect($call['params']['payment_intent'])->toBe('pi_paid')
        ->and(collect($call['headers'])->contains("Idempotency-Key: order-{$this->order->id}-refund"))->toBeTrue();
});

it('refunds and restocks an order only once when it is refunded twice', function () {
    $service = app(OrderLifecycleService::class);
    $service->refund($this->order);

    expect(fn () => $service->refund($this->order))->toThrow(InvalidOrderTransitionException::class);

    expect($this->stripe->callsTo('post', '/v1/refunds'))->toHaveCount(1)
        ->and($this->product->fresh()->stock)->toBe(5);
});

it('lets a customer cancel their own paid order, which refunds and restocks', function () {
    $this->actingAs($this->order->user)->post("/orders/{$this->order->id}/cancel")->assertSessionHasNoErrors();

    expect($this->order->fresh()->status)->toBe('cancelled')
        ->and($this->product->fresh()->stock)->toBe(5)
        ->and($this->stripe->callsTo('post', '/v1/refunds'))->toHaveCount(1);
});

it('does not let a customer cancel an order that has shipped', function () {
    $this->order->update(['status' => 'shipped']);

    $this->actingAs($this->order->user)->post("/orders/{$this->order->id}/cancel")->assertSessionHasErrors('order');

    expect($this->order->fresh()->status)->toBe('shipped')
        ->and($this->stripe->callsTo('post', '/v1/refunds'))->toBeEmpty();
});

it("does not let a customer cancel somebody else's order", function () {
    $this->actingAs(User::factory()->create())->post("/orders/{$this->order->id}/cancel")->assertForbidden();

    expect($this->order->fresh()->status)->toBe('paid')
        ->and($this->stripe->calls)->toBeEmpty();
});
