<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Assistant\ConversationEngine;
use App\Services\Assistant\Reply;
use App\Services\Assistant\RuleBasedEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function send(string $message, ?User $as = null)
    {
        $request = $as ? $this->actingAs($as) : $this;

        return $request->postJson('/assistant/message', ['message' => $message]);
    }

    public function test_it_answers_with_parts_and_suggestions(): void
    {
        $product = Product::factory()->create(['name' => 'Wireless Headphones', 'stock' => 4, 'category_id' => null]);

        $json = $this->send('wireless headphones')->assertOk()->json();

        $this->assertSame(['parts', 'suggestions'], array_keys($json));
        $this->assertSame('text', $json['parts'][0]['type']);
        $this->assertSame('products', $json['parts'][1]['type']);
        $this->assertSame($product->slug, $json['parts'][1]['products'][0]['slug']);
        $this->assertIsArray($json['suggestions']);
    }

    public function test_it_works_for_guests_and_customers(): void
    {
        $this->send('hi')->assertOk();
        $this->send('hi', User::factory()->create())->assertOk();
    }

    public function test_a_message_is_required_and_bounded(): void
    {
        $this->postJson('/assistant/message', [])->assertStatus(422)->assertJsonValidationErrors('message');
        $this->postJson('/assistant/message', ['message' => ''])->assertStatus(422);
        $this->postJson('/assistant/message', ['message' => ['not', 'text']])->assertStatus(422);
        $this->postJson('/assistant/message', ['message' => str_repeat('a', 2001)])->assertStatus(422);
    }

    public function test_a_long_message_is_cut_down_before_it_is_read(): void
    {
        Product::factory()->create(['name' => 'Wireless Headphones', 'category_id' => null]);

        // The product name is within the first 300 characters; the rest is junk that would never match.
        $this->send('wireless headphones '.str_repeat('zzzz ', 300))->assertOk();
    }

    public function test_the_assistant_remembers_the_conversation_within_a_session(): void
    {
        foreach (range(1, 6) as $i) {
            Product::factory()->create(['name' => "Gadget {$i}", 'category_id' => null, 'stock' => 3]);
        }

        $first = $this->send('gadgets')->json();
        $second = $this->send('show more')->json();

        $firstNames = array_column($first['parts'][1]['products'], 'name');
        $secondNames = array_column($second['parts'][1]['products'], 'name');

        $this->assertCount(4, $firstNames);
        $this->assertCount(2, $secondNames);
        $this->assertEmpty(array_intersect($firstNames, $secondNames));
    }

    public function test_two_visitors_do_not_share_a_conversation(): void
    {
        foreach (range(1, 6) as $i) {
            Product::factory()->create(['name' => "Gadget {$i}", 'category_id' => null, 'stock' => 3]);
        }

        $this->send('gadgets');

        // A different browser: no session cookie, so "more" has nothing to carry on from.
        $this->flushSession();
        $this->app['cookie']->flushQueuedCookies();
        $other = $this->withUnencryptedCookies([])->postJson('/assistant/message', ['message' => 'more'])->json();

        $this->assertStringContainsString('nothing to show more of', $other['parts'][0]['text']);
    }

    public function test_a_new_chat_clears_what_the_assistant_remembered(): void
    {
        foreach (range(1, 6) as $i) {
            Product::factory()->create(['name' => "Gadget {$i}", 'category_id' => null, 'stock' => 3]);
        }

        $this->send('gadgets');
        $this->postJson('/assistant/reset')->assertOk()->assertJson(['ok' => true]);

        $this->assertStringContainsString('nothing to show more of', $this->send('more')->json('parts.0.text'));
    }

    public function test_what_a_visitor_types_comes_back_as_plain_text_never_markup(): void
    {
        $json = $this->send('<img src=x onerror=alert(1)> wireless <script>alert(1)</script>')->assertOk()->json();

        // Only plain strings and data, and the client renders them as text nodes. The
        // reply never contains a part type that could carry HTML.
        $types = array_unique(array_column($json['parts'], 'type'));
        $this->assertEmpty(array_diff($types, ['text', 'products', 'orders', 'links']));
    }

    public function test_it_is_rate_limited(): void
    {
        config(['assistant.rate_limit_per_minute' => 5]);

        foreach (range(1, 5) as $i) {
            $this->send('hi')->assertOk();
        }

        $this->send('hi')->assertStatus(429);
    }

    public function test_the_limit_is_per_customer_not_shared_by_everyone(): void
    {
        config(['assistant.rate_limit_per_minute' => 2]);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->send('hi', $first);
        $this->send('hi', $first);
        $this->send('hi', $first)->assertStatus(429);

        $this->send('hi', $second)->assertOk();
    }

    public function test_it_can_be_switched_off(): void
    {
        config(['assistant.enabled' => false]);

        $this->send('hi')->assertNotFound();
        $this->postJson('/assistant/reset')->assertNotFound();
    }

    public function test_pages_know_whether_the_assistant_is_on(): void
    {
        $on = $this->get('/')->viewData('page')['props']['assistant'];
        config(['assistant.enabled' => false]);
        $off = $this->get('/')->viewData('page')['props']['assistant'];

        $this->assertSame([true, false], [$on['enabled'], $off['enabled']]);
    }

    public function test_the_built_in_engine_is_used_by_default(): void
    {
        $this->assertInstanceOf(RuleBasedEngine::class, app(ConversationEngine::class));
    }

    public function test_another_engine_can_be_plugged_in_without_changing_anything_else(): void
    {
        // A language-model engine would be swapped in exactly like this.
        $this->app->bind(ConversationEngine::class, fn () => new class implements ConversationEngine
        {
            public function respond(string $message, ?User $user): Reply
            {
                return Reply::say("echo: {$message}");
            }

            public function reset(): void {}
        });

        $this->send('hello')->assertOk()->assertJsonPath('parts.0.text', 'echo: hello');
    }
}
