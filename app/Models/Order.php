<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'status', 'total_cents', 'discount_cents', 'coupon_id', 'coupon_code',
        'stripe_payment_intent_id', 'shipping_address',
    ];

    protected $casts = ['shipping_address' => 'array'];

    protected $appends = ['subtotal_cents'];

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
}
