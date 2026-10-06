<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    /**
     * Events are an append-only log, so there is no updated_at.
     */
    public const UPDATED_AT = null;

    public const TITLES = [
        'placed' => 'Order placed',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Order cancelled',
        'refunded' => 'Refunded',
    ];

    protected $fillable = ['order_id', 'type', 'message', 'user_id', 'created_at'];

    protected $appends = ['title'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getTitleAttribute(): string
    {
        return self::TITLES[$this->type] ?? ucfirst($this->type);
    }
}
