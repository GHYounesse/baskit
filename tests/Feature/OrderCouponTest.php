<?php

namespace Tests\Feature;

use App\Exceptions\CouponException;
use App\Mail\OrderConfirmation;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Stripe\PaymentIntent;
use Tests\TestCase;

class OrderCouponTest extends TestCase
{
    use RefreshDatabase;

    /** A cart worth $20.00 (2 x $10.00). */
    private function cartFor(User $user): Product
    {
        $product = Product::factory()->create(['price_cents' => 1000, 'stock' => 5]);
        Cart::create(['user_id' => $user->id])->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        return $product;
    }

    private function intent(User $user, int $amount, ?Coupon $coupon = null, string $id = 'pi_coupon_1'): PaymentIntent
    {
        return PaymentIntent::constructFrom([
            'id' => $id,
            'status' => 'succeeded',
            'amount_received' => $amount,
            'metadata' => array_filter(['user_id' => $user->id, 'coupon_id' => $coupon?->id]),
        ]);
    }

    public function test_order_records_the_discount_and_redeems_the_coupon(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->cartFor($user);
        $coupon = Coupon::factory()->percent(25)->create();

        $order = app(OrderService::class)->createFromPaymentIntent($this->intent($user, 1500, $coupon));

        $this->assertSame(1500, $order->total_cents);
        $this->assertSame(500, $order->discount_cents);
        $this->assertSame(2000, $order->subtotal_cents);
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame($coupon->code, $order->coupon_code);
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertNull(Cart::where('user_id', $user->id)->first()->coupon_id);
    }

    public function test_retrying_the_same_payment_does_not_redeem_twice(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->cartFor($user);
        $coupon = Coupon::factory()->percent(25)->create();
        $service = app(OrderService::class);

        $service->createFromPaymentIntent($this->intent($user, 1500, $coupon));
        $service->createFromPaymentIntent($this->intent($user, 1500, $coupon));

        $this->assertSame(1, Order::count());
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_paying_the_undiscounted_amount_with_a_coupon_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user);
        $coupon = Coupon::factory()->percent(25)->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Paid amount does not match cart total.');
        app(OrderService::class)->createFromPaymentIntent($this->intent($user, 2000, $coupon));
    }

    public function test_the_last_redemption_cannot_be_taken_twice(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user);
        $coupon = Coupon::factory()->percent(25)->create(['max_uses' => 1, 'used_count' => 1]);

        try {
            app(OrderService::class)->createFromPaymentIntent($this->intent($user, 1500, $coupon));
            $this->fail('Expected the usage limit to be enforced.');
        } catch (CouponException $e) {
            $this->assertSame('That code has reached its usage limit.', $e->getMessage());
        }

        // Nothing was created or consumed, so the controller can refund cleanly.
        $this->assertSame(0, Order::count());
        $this->assertSame(1, Cart::where('user_id', $user->id)->first()->items()->count());
    }

    public function test_a_coupon_deleted_after_payment_started_is_reported(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user);
        $coupon = Coupon::factory()->create();
        $intent = $this->intent($user, 1800, $coupon);
        $coupon->delete();

        $this->expectException(CouponException::class);
        app(OrderService::class)->createFromPaymentIntent($intent);
    }

    public function test_a_customer_cannot_redeem_a_code_they_already_used(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::factory()->percent(25)->create();
        Order::create(['user_id' => $user->id, 'status' => 'paid', 'total_cents' => 900, 'coupon_id' => $coupon->id]);
        $this->cartFor($user);

        $this->expectException(CouponException::class);
        app(OrderService::class)->createFromPaymentIntent($this->intent($user, 1500, $coupon));
    }

    public function test_confirmation_email_shows_the_discount(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'status' => 'paid', 'total_cents' => 1500,
            'discount_cents' => 500, 'coupon_code' => 'SAVE5',
        ]);

        $html = (new OrderConfirmation($order))->render();

        $this->assertStringContainsString('Discount (SAVE5)', $html);
        $this->assertStringContainsString('$15.00', $html);
    }
}
