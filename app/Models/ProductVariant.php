<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * One purchasable version of a product: a size, a colour or both, with its
 * own SKU and stock. It shares its product's price.
 */
class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'sku', 'size', 'color', 'stock', 'position'];

    protected static function booted(): void
    {
        // The shopping assistant recognises the colours variants come in; keep that list current.
        static::saved(fn () => Cache::forget('assistant.colors'));
        static::deleted(fn () => Cache::forget('assistant.colors'));
    }

    protected $casts = ['stock' => 'integer', 'position' => 'integer'];

    protected $appends = ['label'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * SKUs are case-insensitive; keep them upper-case and trimmed.
     */
    public function setSkuAttribute(string $value): void
    {
        $this->attributes['sku'] = strtoupper(trim($value));
    }

    public function setSizeAttribute(?string $value): void
    {
        $this->attributes['size'] = trim((string) $value);
    }

    public function setColorAttribute(?string $value): void
    {
        $this->attributes['color'] = trim((string) $value);
    }

    /**
     * "M / Blue", "42", "Blue": whichever options this variant has.
     */
    public function getLabelAttribute(): string
    {
        return implode(' / ', array_filter([$this->size, $this->color], fn ($v) => $v !== null && $v !== ''));
    }
}
