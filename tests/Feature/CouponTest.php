<?php

namespace Tests\Feature;

use App\Exceptions\CouponException;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_percent_discount_rounds_down(): void
    {
        $coupon = Coupon::factory()->percent(10)->make();

        $this->assertSame(333, $coupon->discountFor(3333));
    }

    public function test_fixed_discount_is_the_set_amount(): void
    {
        $coupon = Coupon::factory()->fixed(500)->make();

        $this->assertSame(500, $coupon->discountFor(4000));
    }

    public function test_discount_never_takes_the_total_below_the_minimum_charge(): void
    {
        $this->assertSame(950, Coupon::factory()->percent(100)->make()->discountFor(1000));
        $this->assertSame(450, Coupon::factory()->fixed(5000)->make()->discountFor(500));
        $this->assertSame(0, Coupon::factory()->percent(50)->make()->discountFor(40));
    }

    public function test_codes_are_stored_upper_case_and_found_regardless_of_case(): void
    {
        $coupon = Coupon::factory()->create(['code' => '  welcome10 ']);

        $this->assertSame('WELCOME10', $coupon->code);
        $this->assertTrue($coupon->is(Coupon::findByCode('Welcome10')));
    }

    public function test_validation_rejects_inactive_expired_exhausted_and_underspent(): void
    {
        $cases = [
            "That code isn't valid." => Coupon::factory()->make(['is_active' => false]),
            'That code has expired.' => Coupon::factory()->expired()->make(),
            'That code has reached its usage limit.' => Coupon::factory()->make(['max_uses' => 3, 'used_count' => 3]),
            'Spend at least $30.00 to use this code.' => Coupon::factory()->make(['min_subtotal_cents' => 3000]),
        ];

        foreach ($cases as $message => $coupon) {
            try {
                $coupon->validateFor(2000);
                $this->fail("Expected: {$message}");
            } catch (CouponException $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }
    }

    public function test_a_customer_can_only_use_a_code_once(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create();
        Order::create(['user_id' => $user->id, 'status' => 'paid', 'total_cents' => 900, 'coupon_id' => $coupon->id]);

        $this->expectException(CouponException::class);
        $this->expectExceptionMessage("You've already used that code.");
        $coupon->validateFor(2000, $user);
    }

    public function test_status_reflects_why_a_code_cannot_be_used(): void
    {
        $this->assertSame('active', Coupon::factory()->make()->status);
        $this->assertSame('disabled', Coupon::factory()->make(['is_active' => false])->status);
        $this->assertSame('expired', Coupon::factory()->expired()->make()->status);
        $this->assertSame('used up', Coupon::factory()->make(['max_uses' => 1, 'used_count' => 1])->status);
    }

    // ---- Cart endpoints ----

    private function guestCartWith(int $priceCents, int $quantity = 1): Cart
    {
        $product = Product::factory()->create(['price_cents' => $priceCents, 'stock' => 10]);

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => $quantity]);

        return Cart::firstOrFail();
    }

    public function test_guest_can_apply_a_code_and_the_cart_shows_the_discount(): void
    {
        $cart = $this->guestCartWith(2000, 2);
        $coupon = Coupon::factory()->percent(25)->create();

        $this->post('/cart/coupon', ['code' => strtolower($coupon->code)])->assertSessionHasNoErrors();

        $this->assertSame($coupon->id, $cart->fresh()->coupon_id);

        $pricing = $this->get('/cart')->viewData('page')['props']['pricing'];
        $this->assertSame(4000, $pricing['subtotal_cents']);
        $this->assertSame(1000, $pricing['discount_cents']);
        $this->assertSame(3000, $pricing['total_cents']);
        $this->assertSame($coupon->code, $pricing['coupon']['code']);
    }

    public function test_unknown_and_ineligible_codes_are_rejected_with_a_message(): void
    {
        $cart = $this->guestCartWith(1000);

        $this->post('/cart/coupon', ['code' => 'NOPE'])
            ->assertSessionHasErrors(['code' => "That code isn't valid."]);

        $expired = Coupon::factory()->expired()->create();
        $this->post('/cart/coupon', ['code' => $expired->code])
            ->assertSessionHasErrors(['code' => 'That code has expired.']);

        $this->assertNull($cart->fresh()->coupon_id);
    }

    public function test_a_code_needs_something_in_the_cart(): void
    {
        $coupon = Coupon::factory()->create();

        $this->post('/cart/coupon', ['code' => $coupon->code])
            ->assertSessionHasErrors(['code' => 'Add something to your cart first.']);
    }

    public function test_removing_a_code_clears_the_discount(): void
    {
        $cart = $this->guestCartWith(2000);
        $coupon = Coupon::factory()->create();
        $this->post('/cart/coupon', ['code' => $coupon->code]);

        $this->delete('/cart/coupon');

        $this->assertNull($cart->fresh()->coupon_id);
        $this->assertSame(0, $this->get('/cart')->viewData('page')['props']['pricing']['discount_cents']);
    }

    public function test_a_coupon_that_stops_qualifying_is_reported_not_applied(): void
    {
        $cart = $this->guestCartWith(2000);
        $coupon = Coupon::factory()->create(['min_subtotal_cents' => 1500]);
        $this->post('/cart/coupon', ['code' => $coupon->code]);

        $coupon->update(['is_active' => false]);

        $pricing = app(CartService::class)->pricing($cart->fresh());
        $this->assertSame(0, $pricing['discount_cents']);
        $this->assertSame(2000, $pricing['total_cents']);
        $this->assertSame("That code isn't valid.", $pricing['coupon_error']);
    }

    public function test_guest_coupon_follows_them_into_their_account_cart(): void
    {
        $this->guestCartWith(2000);
        $coupon = Coupon::factory()->create();
        $this->post('/cart/coupon', ['code' => $coupon->code]);
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertSame($coupon->id, Cart::where('user_id', $user->id)->first()->coupon_id);
    }

    public function test_trying_codes_is_rate_limited(): void
    {
        $this->guestCartWith(1000);

        foreach (range(1, 10) as $i) {
            $this->post('/cart/coupon', ['code' => "GUESS{$i}"])->assertSessionHasErrors('code');
        }

        $this->post('/cart/coupon', ['code' => 'GUESS11'])->assertStatus(429);
    }
}
