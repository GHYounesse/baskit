<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'product_id', 'product_name', 'reason', 'quantity_change',
        'stock_after', 'order_id', 'user_id', 'note', 'created_at',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'stock_after' => 'integer',
    ];

    protected $appends = ['reason_label'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst($this->reason);
    }
}
