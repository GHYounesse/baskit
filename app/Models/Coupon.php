<?php

namespace App\Models;

use App\Exceptions\CouponException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    /**
     * Stripe rejects charges under 50 cents, so a discount never takes the
     * order total below this (a 100% code still charges 50 cents).
     */
    public const MIN_CHARGE_CENTS = 50;

    protected $fillable = [
        'code', 'type', 'value', 'min_subtotal_cents',
        'max_uses', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $appends = ['label', 'status'];

    /**
     * Codes are case-insensitive: always stored upper-case and trimmed.
     */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', strtoupper(trim($code)))->first();
    }

    /**
     * Short description of the offer, e.g. "10% off" or "$5.00 off".
     */
    public function getLabelAttribute(): string
    {
        return $this->type === self::TYPE_PERCENT
            ? "{$this->value}% off"
            : '$'.number_format($this->value / 100, 2).' off';
    }

    /**
     * What an admin needs to know at a glance.
     */
    public function getStatusAttribute(): string
    {
        return match (true) {
            ! $this->is_active => 'disabled',
            $this->isExpired() => 'expired',
            $this->isExhausted() => 'used up',
            default => 'active',
        };
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }

    public function hasBeenUsedBy(int $userId): bool
    {
        return Order::where('user_id', $userId)
            ->where('coupon_id', $this->id)
            ->whereNotIn('status', Order::RETURNED_STATUSES)
            ->exists();
    }

    /**
     * Whether the code can be applied to a cart with this subtotal. Throws
     * a CouponException with a customer-facing message when it can't.
     * Guests (no user) skip the once-per-customer check; it runs at checkout.
     */
    public function validateFor(int $subtotalCents, ?User $user = null): void
    {
        if (! $this->is_active) {
            throw new CouponException("That code isn't valid.");
        }

        if ($this->isExpired()) {
            throw new CouponException('That code has expired.');
        }

        if ($this->min_subtotal_cents !== null && $subtotalCents < $this->min_subtotal_cents) {
            $min = '$'.number_format($this->min_subtotal_cents / 100, 2);

            throw new CouponException("Spend at least {$min} to use this code.");
        }

        $this->assertRedeemable($user?->id);
    }

    /**
     * The checks that depend on how the code has been used so far. Also run
     * at order time, under a row lock, so two buyers can't both take the
     * last redemption.
     */
    public function assertRedeemable(?int $userId): void
    {
        if ($this->isExhausted()) {
            throw new CouponException('That code has reached its usage limit.');
        }

        if ($userId !== null && $this->hasBeenUsedBy($userId)) {
            throw new CouponException("You've already used that code.");
        }
    }

    /**
     * The discount in cents for a subtotal, never more than leaves
     * MIN_CHARGE_CENTS to pay.
     */
    public function discountFor(int $subtotalCents): int
    {
        $discount = $this->type === self::TYPE_PERCENT
            ? intdiv($subtotalCents * $this->value, 100)
            : $this->value;

        return max(0, min($discount, $subtotalCents - self::MIN_CHARGE_CENTS));
    }
}
