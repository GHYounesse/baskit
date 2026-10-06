<?php

namespace Tests\Feature;

use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\PaymentRefundException;
use App\Mail\OrderRefunded;
use App\Mail\OrderShipped;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderLifecycleService;
use App\Services\Payments\PaymentRefunder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Stands in for Stripe: records what it was asked to refund and can be told to fail.
 */
class FakeRefunder implements PaymentRefunder
{
    /** @var list<array{string, string}> */
    public array $calls = [];

    public bool $fail = false;

    public ?string $result = 're_test_123';

    public function refund(string $paymentIntentId, string $idempotencyKey): ?string
    {
        if ($this->fail) {
            throw new PaymentRefundException('Stripe could not process the refund: card closed.');
        }

        $this->calls[] = [$paymentIntentId, $idempotencyKey];

        return $this->result;
    }
}

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private FakeRefunder $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripe = new FakeRefunder;
        $this->app->instance(PaymentRefunder::class, $this->stripe);
        Mail::fake();
    }

    private function orderWith(Product $product, string $status = 'paid', int $quantity = 2, array $attributes = []): Order
    {
        $order = Order::create($attributes + [
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'total_cents' => $product->price_cents * $quantity,
            'stripe_payment_intent_id' => 'pi_'.uniqid(),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price_cents' => $product->price_cents,
            'quantity' => $quantity,
        ]);

        return $order;
    }

    private function service(): OrderLifecycleService
    {
        return app(OrderLifecycleService::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    // ---- Timeline ----

    public function test_a_new_order_starts_with_a_placed_event(): void
    {
        $order = $this->orderWith(Product::factory()->create());

        $this->assertSame(['placed'], $order->events->pluck('type')->all());
    }

    public function test_the_timeline_lists_events_in_order_and_what_is_still_to_come(): void
    {
        $order = $this->orderWith(Product::factory()->create());
        $this->service()->ship($order, 'UPS', '1Z999');

        $timeline = $order->fresh()->timeline();

        $this->assertSame(['placed', 'shipped'], $timeline['events']->pluck('type')->all());
        $this->assertSame(['Delivered'], $timeline['upcoming']);
        $this->assertSame('Shipped', $timeline['events'][1]->title);
    }

    public function test_finished_orders_have_nothing_upcoming(): void
    {
        $order = $this->orderWith(Product::factory()->create(), 'delivered');

        $this->assertSame([], $order->timeline()['upcoming']);
    }

    // ---- Shipping and delivery ----

    public function test_shipping_records_tracking_a_timeline_event_and_emails_the_customer(): void
    {
        $admin = $this->admin();
        $order = $this->orderWith(Product::factory()->create());

        $this->service()->ship($order, ' UPS ', '1Z999', $admin);

        $order->refresh();
        $this->assertSame('shipped', $order->status);
        $this->assertSame('UPS', $order->tracking_carrier);
        $this->assertSame('1Z999', $order->tracking_number);
        $this->assertNotNull($order->shipped_at);

        $event = $order->events->last();
        $this->assertSame('shipped', $event->type);
        $this->assertSame('Shipped with UPS. Tracking number: 1Z999.', $event->message);
        $this->assertSame($admin->id, $event->user_id);

        Mail::assertQueued(OrderShipped::class, fn ($mail) => $mail->hasTo($order->user->email));
    }

    public function test_tracking_details_are_optional(): void
    {
        $order = $this->orderWith(Product::factory()->create());

        $this->service()->ship($order, '', null);

        $this->assertSame('Order shipped.', $order->fresh()->events->last()->message);
        $this->assertNull($order->fresh()->tracking_number);
    }

    public function test_only_paid_orders_can_be_shipped(): void
    {
        foreach (['pending', 'shipped', 'delivered', 'cancelled', 'refunded'] as $status) {
            $order = $this->orderWith(Product::factory()->create(), $status);

            try {
                $this->service()->ship($order, null, null);
                $this->fail("Shipped a {$status} order");
            } catch (InvalidOrderTransitionException) {
                $this->assertSame($status, $order->fresh()->status);
            }
        }

        Mail::assertNothingQueued();
    }

    public function test_delivery_needs_a_shipped_order(): void
    {
        $paid = $this->orderWith(Product::factory()->create());
        $shipped = $this->orderWith(Product::factory()->create(), 'shipped');

        $this->service()->deliver($shipped);
        $this->assertSame('delivered', $shipped->fresh()->status);
        $this->assertNotNull($shipped->fresh()->delivered_at);
        $this->assertSame('delivered', $shipped->fresh()->events->last()->type);

        $this->expectException(InvalidOrderTransitionException::class);
        $this->service()->deliver($paid);
    }

    // ---- Refunds ----

    public function test_refunding_an_unshipped_order_cancels_it_and_refunds_through_stripe(): void
    {
        $admin = $this->admin();
        $order = $this->orderWith(Product::factory()->create(['price_cents' => 1000]));

        $this->service()->refund($order, $admin, 'Customer changed their mind');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('re_test_123', $order->stripe_refund_id);
        $this->assertNotNull($order->refunded_at);
        $this->assertSame([[$order->stripe_payment_intent_id, "order-{$order->id}-refund"]], $this->stripe->calls);

        $event = $order->events->last();
        $this->assertSame('cancelled', $event->type);
        $this->assertStringContainsString('$20.00 refunded', $event->message);
        $this->assertStringContainsString('Reason: Customer changed their mind', $event->message);

        Mail::assertQueued(OrderRefunded::class, fn ($mail) => $mail->hasTo($order->user->email));
    }

    public function test_refunding_a_shipped_or_delivered_order_marks_it_refunded(): void
    {
        foreach (['shipped', 'delivered'] as $status) {
            $order = $this->orderWith(Product::factory()->create(), $status);

            $this->service()->refund($order);

            $this->assertSame('refunded', $order->fresh()->status);
            $this->assertSame('refunded', $order->fresh()->events->last()->type);
        }
    }

    public function test_restocking_returns_the_items_to_stock_and_can_be_skipped(): void
    {
        $product = Product::factory()->create(['stock' => 3]);

        $this->service()->refund($this->orderWith($product, 'paid', 2), restock: true);
        $this->assertSame(5, $product->fresh()->stock);

        $this->service()->refund($this->orderWith($product, 'delivered', 4), restock: false);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_a_failed_stripe_refund_changes_nothing(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = $this->orderWith($product);
        $this->stripe->fail = true;

        try {
            $this->service()->refund($order);
            $this->fail('Expected the refund to fail');
        } catch (PaymentRefundException $e) {
            $this->assertStringContainsString('card closed', $e->getMessage());
        }

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertNull($order->refunded_at);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(['placed'], $order->events->pluck('type')->all());
        Mail::assertNothingQueued();
    }

    public function test_an_order_cannot_be_refunded_twice(): void
    {
        $order = $this->orderWith(Product::factory()->create());
        $this->service()->refund($order);

        $this->expectException(InvalidOrderTransitionException::class);
        $this->service()->refund($order);
    }

    public function test_orders_without_a_payment_cannot_be_refunded(): void
    {
        $order = $this->orderWith(Product::factory()->create(), 'paid', 1, ['stripe_payment_intent_id' => null]);

        $this->expectException(InvalidOrderTransitionException::class);
        $this->service()->refund($order);
    }

    public function test_an_already_refunded_payment_is_still_recorded(): void
    {
        $this->stripe->result = null; // Stripe: "charge_already_refunded"
        $order = $this->orderWith(Product::factory()->create());

        $this->service()->refund($order);

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNull($order->fresh()->stripe_refund_id);
    }

    public function test_a_refund_gives_the_coupon_back(): void
    {
        $coupon = Coupon::factory()->create(['max_uses' => 1, 'used_count' => 1]);
        $order = $this->orderWith(Product::factory()->create(), 'paid', 1, [
            'coupon_id' => $coupon->id, 'coupon_code' => $coupon->code, 'discount_cents' => 100,
        ]);

        $this->assertTrue($coupon->fresh()->hasBeenUsedBy($order->user_id));

        $this->service()->refund($order);

        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertFalse($coupon->fresh()->hasBeenUsedBy($order->user_id));
    }

    public function test_the_coupon_count_never_goes_below_zero(): void
    {
        $coupon = Coupon::factory()->create(['used_count' => 0]);
        $order = $this->orderWith(Product::factory()->create(), 'paid', 1, ['coupon_id' => $coupon->id]);

        $this->service()->refund($order);

        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_refunded_orders_do_not_count_as_revenue_or_as_a_purchase_for_reviews(): void
    {
        $product = Product::factory()->create();
        $paid = $this->orderWith($product, 'paid', 1);
        $refunded = $this->orderWith($product, 'refunded', 1, ['user_id' => User::factory()->create()->id]);

        $this->assertTrue($product->wasPurchasedBy($paid->user));
        $this->assertFalse($product->wasPurchasedBy($refunded->user));

        $revenue = $this->actingAs($this->admin())->get('/admin')->viewData('page')['props']['stats']['revenue_cents'];
        $this->assertSame($paid->total_cents, $revenue);
    }

    // ---- Provider-side refunds (Stripe dashboard) ----

    private function chargeRefunded(string $paymentIntentId, bool $fullyRefunded = true): TestResponse
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $payload = json_encode([
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'id' => 'ch_test', 'object' => 'charge',
                'payment_intent' => $paymentIntentId, 'refunded' => $fullyRefunded,
            ]],
        ]);
        $time = time();
        $signature = hash_hmac('sha256', "{$time}.{$payload}", 'whsec_test');

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    public function test_a_refund_made_in_the_stripe_dashboard_updates_the_order(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = $this->orderWith($product, 'delivered', 2);

        $this->chargeRefunded($order->stripe_payment_intent_id)->assertOk();

        $order->refresh();
        $this->assertSame('refunded', $order->status);
        $this->assertStringContainsString('refunded from the Stripe dashboard', $order->events->last()->message);
        $this->assertSame(3, $product->fresh()->stock); // goods may not have come back
        Mail::assertQueued(OrderRefunded::class);
    }

    public function test_the_webhook_ignores_partial_refunds_unknown_payments_and_repeats(): void
    {
        $order = $this->orderWith(Product::factory()->create());

        $this->chargeRefunded($order->stripe_payment_intent_id, fullyRefunded: false)->assertOk();
        $this->assertSame('paid', $order->fresh()->status);

        $this->chargeRefunded('pi_unknown')->assertOk();

        // Refund through the app, then Stripe tells us about it: no second event or email.
        $this->service()->refund($order);
        $events = $order->fresh()->events->count();
        $this->chargeRefunded($order->stripe_payment_intent_id)->assertOk();

        $this->assertSame($events, $order->fresh()->events->count());
        Mail::assertQueued(OrderRefunded::class, 1);
    }

    // ---- Admin screens ----

    public function test_admin_can_ship_deliver_and_refund_from_the_order_page(): void
    {
        $admin = $this->admin();
        $order = $this->orderWith(Product::factory()->create(['stock' => 1]));

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/ship", [
            'tracking_carrier' => 'DHL', 'tracking_number' => 'JD0146',
        ])->assertSessionHasNoErrors();
        $this->assertSame('shipped', $order->fresh()->status);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/deliver")->assertSessionHasNoErrors();
        $this->assertSame('delivered', $order->fresh()->status);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/refund", ['reason' => 'Damaged', 'restock' => false])
            ->assertSessionHasNoErrors();
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_the_admin_page_tells_the_ui_which_actions_apply(): void
    {
        $admin = $this->admin();
        $paid = $this->orderWith(Product::factory()->create());
        $done = $this->orderWith(Product::factory()->create(), 'refunded');

        $actions = fn (Order $o) => $this->actingAs($admin)->get("/admin/orders/{$o->id}")->assertOk()
            ->viewData('page')['props']['actions'];

        $this->assertSame(['ship' => true, 'deliver' => false, 'refund' => true], $actions($paid));
        $this->assertSame(['ship' => false, 'deliver' => false, 'refund' => false], $actions($done));
    }

    public function test_an_impossible_action_shows_a_message_instead_of_failing(): void
    {
        $order = $this->orderWith(Product::factory()->create(), 'refunded');

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/ship")
            ->assertSessionHasErrors('order');
    }

    public function test_a_stripe_failure_is_reported_to_the_admin(): void
    {
        $order = $this->orderWith(Product::factory()->create());
        $this->stripe->fail = true;

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/refund")
            ->assertSessionHasErrors('order');

        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_customers_cannot_use_the_admin_order_actions(): void
    {
        $order = $this->orderWith(Product::factory()->create());
        $customer = $order->user;

        $this->actingAs($customer)->get("/admin/orders/{$order->id}")->assertForbidden();
        $this->actingAs($customer)->post("/admin/orders/{$order->id}/ship")->assertForbidden();
        $this->actingAs($customer)->post("/admin/orders/{$order->id}/refund")->assertForbidden();
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_the_old_status_dropdown_endpoint_is_gone(): void
    {
        $order = $this->orderWith(Product::factory()->create());

        $this->actingAs($this->admin())->patch("/admin/orders/{$order->id}", ['status' => 'cancelled'])
            ->assertStatus(405);
    }

    // ---- Customer cancellation ----

    public function test_a_customer_can_cancel_their_own_unshipped_order(): void
    {
        $product = Product::factory()->create(['stock' => 1]);
        $order = $this->orderWith($product, 'paid', 2);

        $this->actingAs($order->user)->post("/orders/{$order->id}/cancel")
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertStringContainsString('Cancelled by the customer', $order->fresh()->events->last()->message);
    }

    public function test_a_failed_refund_shows_the_customer_a_friendly_message_and_changes_nothing(): void
    {
        $order = $this->orderWith(Product::factory()->create());
        $this->stripe->fail = true;

        $response = $this->actingAs($order->user)->post("/orders/{$order->id}/cancel");

        $response->assertSessionHasErrors('order');
        $this->assertStringNotContainsString('Stripe', session('errors')->first('order'));
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_a_customer_cannot_cancel_once_it_has_shipped(): void
    {
        $order = $this->orderWith(Product::factory()->create(), 'shipped');

        $this->actingAs($order->user)->post("/orders/{$order->id}/cancel")->assertSessionHasErrors('order');

        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame([], $this->stripe->calls);
    }

    public function test_a_customer_cannot_cancel_someone_elses_order(): void
    {
        $order = $this->orderWith(Product::factory()->create());

        $this->actingAs(User::factory()->create())->post("/orders/{$order->id}/cancel")->assertForbidden();
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_guests_cannot_cancel_orders(): void
    {
        $order = $this->orderWith(Product::factory()->create());

        $this->post("/orders/{$order->id}/cancel")->assertRedirect('/login');
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_the_order_page_shows_the_timeline_and_whether_cancelling_is_possible(): void
    {
        $paid = $this->orderWith(Product::factory()->create());
        $shipped = $this->orderWith(Product::factory()->create(), 'shipped');

        $props = fn (Order $o) => $this->actingAs($o->user)->get("/orders/{$o->id}")->assertOk()->viewData('page')['props'];

        $this->assertTrue($props($paid)['canCancel']);
        $this->assertCount(1, $props($paid)['timeline']['events']);
        $this->assertSame(['Shipped', 'Delivered'], $props($paid)['timeline']['upcoming']);
        $this->assertFalse($props($shipped)['canCancel']);
    }
}
