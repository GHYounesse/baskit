<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'status', 'total_cents',
        'stripe_payment_intent_id', 'shipping_address',
    ];

    protected $casts = ['shipping_address' => 'array'];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
