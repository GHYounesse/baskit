<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'slug', 'description',
        'price_cents', 'stock', 'image_path', 'is_active',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }
}
