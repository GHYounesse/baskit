<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
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
