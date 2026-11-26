<?php

namespace App\Models;

use App\Jobs\SummarizeProductReviews;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'user_id', 'rating', 'body'];

    protected $casts = ['rating' => 'integer'];

    /**
     * Reviews are public, so expose a short display name instead of the
     * user record.
     */
    protected $hidden = ['user'];

    protected $appends = ['author'];

    protected static function booted(): void
    {
        $refresh = function (Review $review) {
            if (config('ai.enabled')) {
                SummarizeProductReviews::dispatch($review->product_id)->delay(now()->addMinute())->afterCommit();
            }
        };

        static::saved($refresh);
        static::deleted($refresh);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * "Jane Doe" -> "Jane D."; a single name is shown as is. Titles are dropped.
     */
    public function getAuthorAttribute(): string
    {
        // Drop a leading title so "Dr. Jane Doe" reads "Jane D.", not "Dr. D.".
        $name = preg_replace('/^(mr|mrs|ms|miss|mx|dr|prof)\.?\s+/i', '', trim($this->user?->name ?? ''));
        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);

        return match (count($parts)) {
            0 => 'Customer',
            1 => $parts[0],
            default => $parts[0].' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.',
        };
    }
}
