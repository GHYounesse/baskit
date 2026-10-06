<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    /**
     * A few demo codes that exercise the different rules. Safe to run
     * repeatedly: upserted by code, and used_count is left alone.
     */
    public function run(): void
    {
        $coupons = [
            // 10% off anything, one use per customer
            ['code' => 'WELCOME10', 'type' => Coupon::TYPE_PERCENT, 'value' => 10],
            // $5 off orders of $30 or more
            ['code' => 'SAVE5', 'type' => Coupon::TYPE_FIXED, 'value' => 500, 'min_subtotal_cents' => 3000],
            // Limited run: 25% off, first 50 redemptions only
            ['code' => 'FLASH25', 'type' => Coupon::TYPE_PERCENT, 'value' => 25, 'max_uses' => 50],
            // An expired code, handy for seeing the error message
            ['code' => 'SUMMER20', 'type' => Coupon::TYPE_PERCENT, 'value' => 20, 'expires_at' => now()->subMonth()],
        ];

        foreach ($coupons as $coupon) {
            Coupon::updateOrCreate(['code' => $coupon['code']], $coupon + ['is_active' => true]);
        }
    }
}
