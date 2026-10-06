<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Order extends Model
{
    /**
     * Orders that were paid for and then given back. They never count as
     * revenue, can't be reviewed against, and release their coupon.
     */
    public const RETURNED_STATUSES = ['cancelled', 'refunded'];

    protected $fillable = [
        'user_id', 'status', 'total_cents', 'discount_cents', 'coupon_id', 'coupon_code',
        'stripe_payment_intent_id', 'shipping_address',
        'tracking_carrier', 'tracking_number',
        'shipped_at', 'delivered_at', 'refunded_at', 'stripe_refund_id',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    protected $appends = ['subtotal_cents'];

    protected static function booted(): void
    {
        // Every order starts its timeline the moment it exists, however it was created.
        static::created(function (Order $order) {
            $order->events()->create(['type' => 'placed', 'created_at' => $order->created_at]);
        });
    }

    /**
     * What the items cost before any coupon. total_cents is what was paid.
     */
    public function getSubtotalCentsAttribute(): int
    {
        return $this->total_cents + $this->discount_cents;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events()
    {
        return $this->hasMany(OrderEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function canBeShipped(): bool
    {
        return $this->status === 'paid';
    }

    public function canBeDelivered(): bool
    {
        return $this->status === 'shipped';
    }

    /**
     * Refundable while the customer's money is still with us and we have a
     * Stripe payment to send it back through.
     */
    public function canBeRefunded(): bool
    {
        return in_array($this->status, ['paid', 'shipped', 'delivered'], true)
            && $this->stripe_payment_intent_id !== null;
    }

    /**
     * Customers can cancel on their own only until the order ships.
     */
    public function canBeCancelledByCustomer(): bool
    {
        return $this->status === 'paid' && $this->stripe_payment_intent_id !== null;
    }

    /**
     * What happened (oldest first) and what is still to come, for the timeline.
     *
     * @return array{events: Collection, upcoming: list<string>}
     */
    public function timeline(): array
    {
        $upcoming = match ($this->status) {
            'paid' => ['Shipped', 'Delivered'],
            'shipped' => ['Delivered'],
            default => [],
        };

        return ['events' => $this->events()->get(), 'upcoming' => $upcoming];
    }
}
