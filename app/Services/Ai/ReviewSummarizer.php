<?php

namespace App\Services\Ai;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Support\Collection;

/**
 * Writes the short "What customers say" digest shown on a product page, from
 * that product's written reviews. The summary is stored on the product and
 * only rewritten when the reviews it was based on change.
 */
class ReviewSummarizer
{
    public function __construct(private LlmClient $llm) {}

    /**
     * Brings the stored summary up to date. Returns true when it changed.
     *
     * @throws LlmException when the model cannot answer; the old summary stays.
     */
    public function refresh(Product $product, bool $force = false): bool
    {
        $reviews = $this->writtenReviews($product);

        // Too few reviews to say anything fair: show no summary at all.
        if ($reviews->count() < config('ai.review_summary.min_reviews')) {
            return $this->store($product, null, null);
        }

        $hash = $this->fingerprint($reviews);

        if (! $force && $product->review_summary !== null && $product->review_summary_hash === $hash) {
            return false;
        }

        return $this->store($product, $this->write($product, $reviews), $hash);
    }

    /**
     * The newest reviews that have text, which are the ones the model reads.
     *
     * @return Collection<int, Review>
     */
    private function writtenReviews(Product $product): Collection
    {
        return $product->reviews()
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->latest()
            ->limit(config('ai.review_summary.max_reviews'))
            ->get(['id', 'rating', 'body', 'updated_at']);
    }

    /**
     * Changes whenever a review is added, edited or removed.
     */
    private function fingerprint(Collection $reviews): string
    {
        return md5($reviews->map(fn (Review $r) => $r->id.':'.$r->updated_at?->timestamp)->implode('|'));
    }

    /**
     * @param  Collection<int, Review>  $reviews
     *
     * @throws LlmException
     */
    private function write(Product $product, Collection $reviews): string
    {
        $system = 'Write a short digest of what customers say in these reviews, as plain text.';

        $limit = config('ai.review_summary.max_review_chars');

        $lines = $reviews->map(fn (Review $r) => "- ({$r->rating}/5) ".mb_substr(preg_replace('/\s+/', ' ', trim($r->body)), 0, $limit));

        $prompt = "Product: {$product->name}\n\nReviews, newest first:\n".$lines->implode("\n");

        return trim($this->llm->complete($system, $prompt));
    }

    private function store(Product $product, ?string $summary, ?string $hash): bool
    {
        if ($product->review_summary === $summary && $product->review_summary_hash === $hash) {
            return false;
        }

        // Quietly: this is not an edit of the product, so no search reindex or audit entry.
        $product->forceFill([
            'review_summary' => $summary,
            'review_summary_hash' => $hash,
            'review_summary_at' => $summary === null ? null : now(),
        ])->saveQuietly();

        return true;
    }
}
