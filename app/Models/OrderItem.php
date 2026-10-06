<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'product_name',
        'variant_label', 'sku', 'unit_price_cents', 'quantity',
    ];

    protected $appends = ['display_name'];

    /**
     * What to call this line: "Cotton T-Shirt (M / Blue)", or just the name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->variant_label ? "{$this->product_name} ({$this->variant_label})" : $this->product_name;
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
