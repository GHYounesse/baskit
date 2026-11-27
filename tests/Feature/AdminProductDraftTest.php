<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\LlmClient;
use App\Services\Ai\LlmException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminProductDraftTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /**
     * Swap the real model for one that answers with $reply (or fails).
     */
    private function fakeLlm(string|LlmException $reply): void
    {
        $this->app->bind(LlmClient::class, fn () => new class($reply) implements LlmClient
        {
            public function __construct(private string|LlmException $reply) {}

            public function complete(string $system, string $prompt, ?string $imageData = null): string
            {
                if ($this->reply instanceof LlmException) {
                    throw $this->reply;
                }

                return $this->reply;
            }
        });
    }

    public function test_it_returns_a_draft_and_saves_nothing(): void
    {
        config(['ai.enabled' => true]);
        $mugs = Category::create(['name' => 'Mugs', 'slug' => 'mugs']);
        $this->fakeLlm('Sure! ```json {"name": "Blue ceramic mug", "description": "A 350 ml mug.", "category_id": '.$mugs->id.'} ```');

        $this->actingAs($this->admin())
            ->postJson('/admin/products/draft', ['keywords' => 'blue mug'])
            ->assertOk()
            ->assertExactJson([
                'name' => 'Blue ceramic mug',
                'description' => 'A 350 ml mug.',
                'category_id' => $mugs->id,
            ]);

        $this->assertSame(0, Product::count());
    }

    public function test_an_unknown_category_id_from_the_model_is_dropped(): void
    {
        config(['ai.enabled' => true]);
        $this->fakeLlm('{"name": "Lamp", "description": "A lamp.", "category_id": 9999}');

        $this->actingAs($this->admin())
            ->postJson('/admin/products/draft', ['keywords' => 'lamp'])
            ->assertOk()
            ->assertJsonPath('category_id', null);
    }

    public function test_an_unreadable_answer_gives_a_calm_error(): void
    {
        config(['ai.enabled' => true]);
        $this->fakeLlm('I cannot help with that.');

        $this->actingAs($this->admin())
            ->postJson('/admin/products/draft', ['keywords' => 'lamp'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The AI answer could not be read. Try again.');
    }

    public function test_a_busy_model_gives_a_calm_error(): void
    {
        config(['ai.enabled' => true]);
        $this->fakeLlm(new LlmException('The free AI model is busy right now. Try again in a minute.'));

        $this->actingAs($this->admin())
            ->postJson('/admin/products/draft', ['keywords' => 'lamp'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The free AI model is busy right now. Try again in a minute.');
    }

    public function test_it_needs_keywords_or_a_photo(): void
    {
        config(['ai.enabled' => true]);

        $this->actingAs($this->admin())
            ->postJson('/admin/products/draft', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['keywords', 'image']);
    }

    public function test_it_is_admin_only(): void
    {
        config(['ai.enabled' => true]);
        $this->fakeLlm('{"name": "x", "description": "y", "category_id": null}');

        $this->actingAs(User::factory()->create())
            ->postJson('/admin/products/draft', ['keywords' => 'lamp'])
            ->assertForbidden();
    }

    public function test_a_guest_cannot_use_it(): void
    {
        config(['ai.enabled' => true]);

        $this->postJson('/admin/products/draft', ['keywords' => 'lamp'])->assertUnauthorized();
    }

    public function test_it_does_not_exist_until_an_api_key_is_set(): void
    {
        config(['ai.enabled' => false]);

        $this->actingAs($this->admin())
            ->postJson('/admin/products/draft', ['keywords' => 'lamp'])
            ->assertNotFound();

        $this->actingAs($this->admin())
            ->get('/admin/products/create')
            ->assertInertia(fn ($page) => $page->where('aiEnabled', false));
    }

    public function test_a_photo_is_accepted_instead_of_keywords(): void
    {
        config(['ai.enabled' => true]);
        $this->fakeLlm('{"name": "Lamp", "description": "A lamp.", "category_id": null}');

        $this->actingAs($this->admin())
            ->post('/admin/products/draft', ['image' => UploadedFile::fake()->createWithContent('lamp.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='))], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('name', 'Lamp');
    }

    public function test_gemini_client_sends_the_key_in_a_header_and_reads_the_reply(): void
    {
        config(['ai.enabled' => true, 'ai.gemini.key' => 'gem-key', 'ai.gemini.model' => 'gemini-flash-latest']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'hello ']]]]],
        ])]);

        $this->assertSame('hello ', app(GeminiClient::class)->complete('sys', 'prompt'));

        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'gem-key')
            && ! str_contains($request->url(), 'gem-key')
            && str_ends_with($request->url(), '/models/gemini-flash-latest:generateContent')
            && $request['system_instruction']['parts'][0]['text'] === 'sys'
            && $request['contents'][0]['parts'][0]['text'] === 'prompt');
    }

    public function test_gemini_client_sends_a_photo_as_inline_data(): void
    {
        config(['ai.enabled' => true, 'ai.gemini.key' => 'gem-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
        ])]);

        app(GeminiClient::class)->complete('sys', 'prompt', 'data:image/png;base64,AAAA');

        Http::assertSent(fn ($request) => $request['contents'][0]['parts'][1]['inline_data'] === ['mime_type' => 'image/png', 'data' => 'AAAA']);
    }

    public function test_gemini_answer_without_text_gives_a_friendly_error(): void
    {
        config(['ai.enabled' => true, 'ai.gemini.key' => 'gem-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'SAFETY']]])]);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('empty answer');

        app(GeminiClient::class)->complete('sys', 'prompt');
    }

    public function test_gemini_rate_limit_gives_a_friendly_error(): void
    {
        config(['ai.enabled' => true, 'ai.gemini.key' => 'gem-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 429)]);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('busy right now');

        app(GeminiClient::class)->complete('sys', 'prompt');
    }
}
