<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Ai\LlmException;
use App\Services\Ai\ReviewSummarizer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Rewrites one product's review summary. Unique per product, so a burst of new
 * reviews is answered by one model call that reads all of them.
 */
class SummarizeProductReviews implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct(public int $productId, public bool $force = false) {}

    public function uniqueId(): string
    {
        return (string) $this->productId;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(ReviewSummarizer $summarizer): void
    {
        $product = Product::find($this->productId);

        if (! $product || ! config('ai.enabled')) {
            return;
        }

        $summarizer->refresh($product, $this->force);
    }

    public function failed(?\Throwable $exception): void
    {
        // The shop works without a summary, and the old one stays, so this is only worth a log line.
        if ($exception instanceof LlmException) {
            Log::warning("Review summary for product {$this->productId} was not updated: {$exception->getMessage()}");
        }
    }
}
