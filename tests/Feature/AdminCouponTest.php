<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCouponTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_customers_cannot_manage_coupons(): void
    {
        $customer = User::factory()->create();
        $coupon = Coupon::factory()->create();

        $this->actingAs($customer)->get('/admin/coupons')->assertForbidden();
        $this->actingAs($customer)->post('/admin/coupons', [])->assertForbidden();
        $this->actingAs($customer)->put("/admin/coupons/{$coupon->id}", [])->assertForbidden();
        $this->actingAs($customer)->delete("/admin/coupons/{$coupon->id}")->assertForbidden();
    }

    public function test_admin_can_list_create_update_and_delete(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/coupons')->assertOk();

        $this->actingAs($admin)->post('/admin/coupons', [
            'code' => ' spring15 ', 'type' => 'percent', 'value' => 15,
            'min_subtotal_cents' => 2000, 'max_uses' => 100, 'is_active' => true,
        ])->assertRedirect('/admin/coupons');

        $coupon = Coupon::firstWhere('code', 'SPRING15');
        $this->assertNotNull($coupon);
        $this->assertSame(2000, $coupon->min_subtotal_cents);

        $this->actingAs($admin)->put("/admin/coupons/{$coupon->id}", [
            'code' => 'SPRING15', 'type' => 'fixed', 'value' => 500, 'is_active' => false,
        ])->assertRedirect('/admin/coupons');

        $this->assertSame('fixed', $coupon->fresh()->type);
        $this->assertFalse($coupon->fresh()->is_active);

        $this->actingAs($admin)->delete("/admin/coupons/{$coupon->id}")->assertRedirect();
        $this->assertModelMissing($coupon);
    }

    public function test_validation_rules(): void
    {
        $admin = $this->admin();
        Coupon::factory()->create(['code' => 'TAKEN']);

        $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'taken', 'type' => 'percent', 'value' => 150,
        ])->assertSessionHasErrors(['code', 'value']);

        $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'bad code!', 'type' => 'bogus', 'value' => 0,
        ])->assertSessionHasErrors(['code', 'type', 'value']);

        // A fixed amount is in cents, so it may exceed 100.
        $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'BIG', 'type' => 'fixed', 'value' => 2500,
        ])->assertSessionHasNoErrors();
    }

    public function test_a_coupon_can_keep_its_own_code_when_edited(): void
    {
        $admin = $this->admin();
        $coupon = Coupon::factory()->create(['code' => 'KEEPME']);

        $this->actingAs($admin)->put("/admin/coupons/{$coupon->id}", [
            'code' => 'keepme', 'type' => 'percent', 'value' => 20,
        ])->assertSessionHasNoErrors();

        $this->assertSame(20, $coupon->fresh()->value);
    }
}
