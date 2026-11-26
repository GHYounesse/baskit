<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    /**
     * An append-only log: there is no updated_at, and rows are not edited.
     */
    public const UPDATED_AT = null;

    public const SALE = 'sale';

    public const RETURN = 'return';

    public const ADJUSTMENT = 'adjustment';

    public const INITIAL = 'initial';

    public const BASELINE = 'baseline';

    /**
     * Reason code => what an admin reads.
     */
    public const REASONS = [
        self::SALE => 'Sold',
        self::RETURN => 'Returned to stock',
        self::ADJUSTMENT => 'Manual adjustment',
        self::INITIAL => 'Product created',
        self::BASELINE => 'Opening balance',
    ];

    protected $fillable = [
        'product_id', 'product_name', 'product_variant_id', 'variant_label', 'reason', 'quantity_change',
        'stock_after', 'order_id', 'user_id', 'note', 'created_at',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'stock_after' => 'integer',
    ];

    protected $appends = ['reason_label'];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst($this->reason);
    }
}
