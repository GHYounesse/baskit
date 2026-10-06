<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE-####')),
            'type' => Coupon::TYPE_PERCENT,
            'value' => 10,
            'is_active' => true,
        ];
    }

    public function fixed(int $cents): static
    {
        return $this->state(['type' => Coupon::TYPE_FIXED, 'value' => $cents]);
    }

    public function percent(int $percent): static
    {
        return $this->state(['type' => Coupon::TYPE_PERCENT, 'value' => $percent]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
