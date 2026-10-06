<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'slug', 'description',
        'price_cents', 'stock', 'image_path', 'is_active',
    ];

    protected $appends = ['image_url'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Whether the user has a (non-cancelled) order containing this product.
     * This is what entitles them to review it.
     */
    public function wasPurchasedBy(User $user): bool
    {
        return OrderItem::where('product_id', $this->id)
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id)->where('status', '!=', 'cancelled'))
            ->exists();
    }

    /**
     * Review count, average (one decimal, null when unreviewed) and how many
     * reviews gave each star rating.
     *
     * @return array{count: int, average: ?float, distribution: array<int, int>}
     */
    public function ratingSummary(): array
    {
        $counts = $this->reviews()->selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating');

        $distribution = [];
        foreach ([5, 4, 3, 2, 1] as $stars) {
            $distribution[$stars] = (int) ($counts[$stars] ?? 0);
        }

        $count = array_sum($distribution);
        $sum = array_sum(array_map(fn ($stars, $n) => $stars * $n, array_keys($distribution), $distribution));

        return [
            'count' => $count,
            'average' => $count ? round($sum / $count, 1) : null,
            'distribution' => $distribution,
        ];
    }

    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        // Seeded demo products point at an external stock photo URL rather
        // than an uploaded file, so pass those through unchanged.
        if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://')) {
            return $this->image_path;
        }

        return Storage::disk('public')->url($this->image_path);
    }
}
