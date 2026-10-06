<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopSearchTest extends TestCase
{
    use RefreshDatabase;

    private function shop(array $query = []): array
    {
        return $this->get('/?'.http_build_query($query))->assertOk()->viewData('page')['props'];
    }

    private function names(array $props): array
    {
        return array_column($props['products']['data'], 'name');
    }

    public function test_results_are_ranked_by_best_match(): void
    {
        Product::factory()->create(['name' => 'Travel Case', 'description' => 'Holds a speaker.']);
        Product::factory()->create(['name' => 'Bluetooth Speaker', 'description' => 'Loud.']);

        $props = $this->shop(['search' => 'speaker']);

        $this->assertSame(['Bluetooth Speaker', 'Travel Case'], $this->names($props));
        $this->assertSame('relevance', $props['filters']['sort']);
    }

    public function test_a_chosen_sort_still_wins_over_relevance(): void
    {
        Product::factory()->create(['name' => 'Cheap Speaker', 'price_cents' => 1000]);
        Product::factory()->create(['name' => 'Pricey Speaker', 'price_cents' => 9000]);

        $props = $this->shop(['search' => 'speaker', 'sort' => 'price_desc']);

        $this->assertSame(['Pricey Speaker', 'Cheap Speaker'], $this->names($props));
    }

    public function test_search_combines_with_a_category_filter(): void
    {
        $audio = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $toys = Category::create(['name' => 'Toys', 'slug' => 'toys']);
        Product::factory()->create(['name' => 'Toy Speaker', 'category_id' => $toys->id]);
        Product::factory()->create(['name' => 'Studio Speaker', 'category_id' => $audio->id]);

        $this->assertSame(['Studio Speaker'], $this->names($this->shop(['search' => 'speaker', 'category' => 'audio'])));
    }

    public function test_search_matches_words_in_any_order_and_prefixes(): void
    {
        Product::factory()->create(['name' => 'Wireless Headphones']);

        $this->assertSame(['Wireless Headphones'], $this->names($this->shop(['search' => 'head wireless'])));
        $this->assertSame(['Wireless Headphones'], $this->names($this->shop(['search' => 'wire'])));
    }

    public function test_a_search_with_no_results_offers_a_correction(): void
    {
        Product::factory()->create(['name' => 'Wireless Headphones']);

        $props = $this->shop(['search' => 'headphnes']);

        $this->assertSame([], $this->names($props));
        $this->assertSame('headphones', $props['correction']);
    }

    public function test_there_is_no_correction_when_results_exist_or_when_not_searching(): void
    {
        Product::factory()->create(['name' => 'Wireless Headphones']);

        $this->assertNull($this->shop(['search' => 'headphones'])['correction']);
        $this->assertNull($this->shop()['correction']);
    }

    public function test_results_paginate_in_rank_order(): void
    {
        foreach (range(1, 14) as $i) {
            Product::factory()->create(['name' => 'Gadget '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $first = $this->shop(['search' => 'gadget']);
        $second = $this->shop(['search' => 'gadget', 'page' => 2]);

        $this->assertCount(12, $first['products']['data']);
        $this->assertCount(2, $second['products']['data']);
        $this->assertSame(14, $first['products']['total']);
        $this->assertEmpty(array_intersect($this->names($first), $this->names($second)));
    }

    public function test_odd_search_text_does_not_break_the_page(): void
    {
        Product::factory()->create(['name' => 'Wireless Headphones']);

        foreach (['"', '*', "'; DROP TABLE products; --", 'NEAR(', '%', '   '] as $nasty) {
            $this->get('/?'.http_build_query(['search' => $nasty]))->assertOk();
        }
    }

    public function test_without_a_search_the_shop_behaves_as_before(): void
    {
        Product::factory()->create(['name' => 'Older']);
        $this->travel(1)->minutes();
        Product::factory()->create(['name' => 'Newer']);

        $props = $this->shop();

        $this->assertSame(['Newer', 'Older'], $this->names($props));
        $this->assertNull($props['filters']['sort']);
    }
}
