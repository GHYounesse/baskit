<?php

namespace App\Console\Commands;

use App\Jobs\SummarizeProductReviews;
use App\Models\Product;
use Illuminate\Console\Command;

class SummarizeReviews extends Command
{
    protected $signature = 'reviews:summarize {--force : Rewrite summaries even when the reviews have not changed}';

    protected $description = 'Queue an AI review summary for every product that has reviews';

    public function handle(): int
    {
        if (! config('ai.enabled')) {
            $this->error('AI helpers are off. Set GEMINI_API_KEY first.');

            return self::FAILURE;
        }

        $queued = 0;

        // Spread the jobs out so a free model plan's rate limit is not hit.
        Product::whereHas('reviews')->pluck('id')->each(function (int $id) use (&$queued) {
            SummarizeProductReviews::dispatch($id, (bool) $this->option('force'))->delay(now()->addSeconds($queued * 6));
            $queued++;
        });

        $this->info("Queued {$queued} product(s). A queue worker writes the summaries.");

        return self::SUCCESS;
    }
}
