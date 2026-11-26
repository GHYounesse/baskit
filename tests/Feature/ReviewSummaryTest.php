<?php

namespace Tests\Feature;

use App\Jobs\SummarizeProductReviews;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Ai\LlmClient;
use App\Services\Ai\LlmException;
use App\Services\Ai\ReviewSummarizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReviewSummaryTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> The prompts the fake model was asked. */
    private array $prompts = [];

    private function fakeLlm(string|LlmException $reply): void
    {
        $this->prompts = [];
        $test = $this;

        $this->app->bind(LlmClient::class, fn () => new class($reply, $test) implements LlmClient
        {
            public function __construct(private string|LlmException $reply, private ReviewSummaryTest $test) {}

            public function complete(string $system, string $prompt, ?string $imageData = null): string
            {
                $this->test->recordPrompt($prompt);

                if ($this->reply instanceof LlmException) {
                    throw $this->reply;
                }

                return $this->reply;
            }
        });
    }

    public function recordPrompt(string $prompt): void
    {
        $this->prompts[] = $prompt;
    }

    private function productWithReviews(int $written, int $silent = 0): Product
    {
        $product = Product::factory()->create(['category_id' => null]);

        foreach (range(1, $written + $silent) as $i) {
            Review::create([
                'product_id' => $product->id,
                'user_id' => User::factory()->create()->id,
                'rating' => 5,
                'body' => $i <= $written ? "Review number {$i}, works well." : null,
            ]);
        }

        return $product->fresh();
    }

    public function test_it_stores_a_summary_written_from_the_reviews(): void
    {
        $this->fakeLlm('Customers like how well it works.');
        $product = $this->productWithReviews(3);

        $changed = app(ReviewSummarizer::class)->refresh($product);

        $this->assertTrue($changed);
        $this->assertSame('Customers like how well it works.', $product->fresh()->review_summary);
        $this->assertStringContainsString('Review number 2', $this->prompts[0]);
    }

    public function test_ratings_without_text_do_not_count_and_too_few_reviews_get_no_summary(): void
    {
        $this->fakeLlm('Should never be used.');
        $product = $this->productWithReviews(2, silent: 4);

        app(ReviewSummarizer::class)->refresh($product);

        $this->assertNull($product->fresh()->review_summary);
        $this->assertSame([], $this->prompts);
    }
}
