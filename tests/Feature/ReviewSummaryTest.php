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

    public function test_an_unchanged_set_of_reviews_does_not_call_the_model_again(): void
    {
        $this->fakeLlm('Customers like it.');
        $product = $this->productWithReviews(3);
        $summarizer = app(ReviewSummarizer::class);

        $summarizer->refresh($product);
        $again = $summarizer->refresh($product->fresh());

        $this->assertFalse($again);
        $this->assertCount(1, $this->prompts);
    }

    public function test_a_new_review_makes_it_write_the_summary_again(): void
    {
        $this->fakeLlm('First version.');
        $product = $this->productWithReviews(3);
        app(ReviewSummarizer::class)->refresh($product);

        Review::create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'rating' => 4, 'body' => 'A fourth opinion.']);
        $this->fakeLlm('Second version.');
        app(ReviewSummarizer::class)->refresh($product->fresh());

        $this->assertSame('Second version.', $product->fresh()->review_summary);
    }

    public function test_the_reply_is_reduced_to_plain_text(): void
    {
        $this->fakeLlm("**Great** <script>alert(1)</script> value, see https://evil.example now.\n- and sturdy");
        $product = $this->productWithReviews(3);

        app(ReviewSummarizer::class)->refresh($product);

        $summary = $product->fresh()->review_summary;
        $this->assertStringNotContainsString('<', $summary);
        $this->assertStringNotContainsString('http', $summary);
        $this->assertStringNotContainsString('*', $summary);
    }

    public function test_a_failing_model_keeps_the_old_summary(): void
    {
        $this->fakeLlm('Original summary.');
        $product = $this->productWithReviews(3);
        app(ReviewSummarizer::class)->refresh($product);

        Review::create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'rating' => 2, 'body' => 'Changed my mind.']);
        $this->fakeLlm(new LlmException('down'));

        try {
            app(ReviewSummarizer::class)->refresh($product->fresh());
            $this->fail('Expected the model error to surface so the job can retry.');
        } catch (LlmException) {
            // expected
        }

        $this->assertSame('Original summary.', $product->fresh()->review_summary);
    }

    public function test_the_summary_is_sent_to_the_product_page_but_not_leaked_elsewhere(): void
    {
        $this->fakeLlm('Customers like it.');
        $product = $this->productWithReviews(3);
        app(ReviewSummarizer::class)->refresh($product);

        $this->get("/products/{$product->slug}")
            ->assertInertia(fn ($page) => $page
                ->where('reviewSummary', 'Customers like it.')
                ->missing('product.review_summary'));
    }

    public function test_reviews_queue_a_rewrite_only_when_ai_is_on(): void
    {
        Queue::fake();
        $product = Product::factory()->create(['category_id' => null]);
        $user = User::factory()->create();

        config(['ai.enabled' => false]);
        Review::create(['product_id' => $product->id, 'user_id' => $user->id, 'rating' => 5, 'body' => 'Nice.']);
        Queue::assertNothingPushed();

        config(['ai.enabled' => true]);
        Review::create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'rating' => 5, 'body' => 'Nice too.']);
        Queue::assertPushed(SummarizeProductReviews::class, fn ($job) => $job->productId === $product->id);
    }
}
