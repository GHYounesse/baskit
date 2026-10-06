<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\Search\StoreSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    private function suggest(string $query): array
    {
        return $this->getJson('/search/suggest?'.http_build_query(['q' => $query]))->assertOk()->json();
    }

    public function test_it_suggests_matching_products_with_what_the_dropdown_needs(): void
    {
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        $product = Product::factory()->create([
            'name' => 'Wireless Headphones', 'category_id' => $category->id, 'price_cents' => 14999, 'image_path' => 'https://example.test/h.jpg',
        ]);
        Product::factory()->create(['name' => 'Cotton T-Shirt']);

        $result = $this->suggest('head');

        $this->assertSame('head', $result['query']);
        $this->assertSame(1, $result['total']);
        $this->assertSame([[
            'id' => $product->id,
            'name' => 'Wireless Headphones',
            'slug' => $product->slug,
            'price_cents' => 14999,
            'image_url' => 'https://example.test/h.jpg',
            'category' => 'Electronics',
        ]], $result['products']);
        $this->assertNull($result['correction']);
    }

    public function test_it_shows_at_most_six_products_but_reports_the_full_count(): void
    {
        foreach (range(1, 9) as $i) {
            Product::factory()->create(['name' => "Gadget {$i}"]);
        }

        $result = $this->suggest('gadget');

        $this->assertCount(6, $result['products']);
        $this->assertSame(9, $result['total']);
    }

    public function test_it_suggests_matching_categories(): void
    {
        Category::create(['name' => 'Home & Kitchen', 'slug' => 'home-kitchen']);
        Category::create(['name' => 'Books', 'slug' => 'books']);

        $result = $this->suggest('kitch');

        $this->assertSame([['name' => 'Home & Kitchen', 'slug' => 'home-kitchen']], $result['categories']);
    }

    public function test_the_best_match_comes_first(): void
    {
        Product::factory()->create(['name' => 'Travel Case', 'description' => 'Holds a speaker.']);
        $named = Product::factory()->create(['name' => 'Bluetooth Speaker', 'description' => 'Loud.']);

        $this->assertSame($named->id, $this->suggest('speaker')['products'][0]['id']);
    }

    public function test_very_short_or_empty_queries_return_nothing_without_searching(): void
    {
        Product::factory()->create(['name' => 'Apple Watch']);

        foreach (['', ' ', 'a'] as $short) {
            $result = $this->suggest($short);

            $this->assertSame([], $result['products']);
            $this->assertSame(0, $result['total']);
        }
    }

    public function test_a_misspelling_with_no_results_gets_a_correction(): void
    {
        Product::factory()->create(['name' => 'Wireless Headphones']);

        $result = $this->suggest('headphnes');

        $this->assertSame(0, $result['total']);
        $this->assertSame('headphones', $result['correction']);
    }

    public function test_corrections_fix_only_the_misspelt_word(): void
    {
        Product::factory()->create(['name' => 'Wireless Headphones']);

        $this->assertSame('wireless headphones', app(StoreSearch::class)->correction('wirelesz headphnes'));
        $this->assertSame('wireless headphones', app(StoreSearch::class)->correction('wireless headphnes'));
    }

    public function test_no_correction_for_a_word_still_being_typed_or_far_from_anything(): void
    {
        Product::factory()->create(['name' => 'Wireless Headphones']);
        $search = app(StoreSearch::class);

        $this->assertNull($search->correction('headph'));      // the start of a real word
        $this->assertNull($search->correction('headphones'));  // already right
        $this->assertNull($search->correction('zzzzzzzz'));    // nothing close
        $this->assertNull($search->correction('ab'));          // too short to guess
    }

    public function test_the_vocabulary_follows_the_catalogue(): void
    {
        $search = app(StoreSearch::class);
        $this->assertNull($search->correction('thermoz'));

        Product::factory()->create(['name' => 'Steel Thermos']);

        $this->assertSame('thermos', $search->correction('thermoz'));
    }

    public function test_hidden_products_are_not_suggested_or_used_for_corrections(): void
    {
        Product::factory()->create(['name' => 'Secret Prototype', 'is_active' => false]);

        $result = $this->suggest('secret');

        $this->assertSame([], $result['products']);
        $this->assertNull($this->suggest('secrt')['correction']);
    }

    public function test_the_endpoint_is_rate_limited(): void
    {
        foreach (range(1, 120) as $i) {
            $this->getJson('/search/suggest?q=ab')->assertOk();
        }

        $this->getJson('/search/suggest?q=ab')->assertStatus(429);
    }

    public function test_it_works_for_guests_and_ignores_overlong_input(): void
    {
        Product::factory()->create(['name' => 'Wireless Headphones']);

        $this->getJson('/search/suggest?q='.str_repeat('x', 5000))->assertOk();
        $this->assertCount(1, $this->suggest('headphones')['products']);
    }
}
