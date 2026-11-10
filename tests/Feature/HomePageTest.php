<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    private function product(Category $category, array $attributes = []): Product
    {
        return Product::factory()->create($attributes + [
            'category_id' => $category->id,
            'is_active' => true,
            'stock' => 5,
            'image_path' => 'https://images.example.com/photo.jpg',
        ]);
    }

    private function props(?User $user = null): array
    {
        $response = ($user ? $this->actingAs($user) : $this)->get('/')->assertOk();

        return $response->viewData('page')['props'];
    }

    public function test_the_home_page_is_the_root_and_the_catalogue_lives_at_shop(): void
    {
        $this->assertSame('Home', $this->get('/')->viewData('page')['component']);
        $this->assertSame('Shop/Index', $this->get('/shop')->viewData('page')['component']);
        $this->assertSame('/shop', route('shop.index', [], false));
    }

    public function test_it_works_for_an_empty_store(): void
    {
        $props = $this->props();

        $this->assertCount(0, $props['hero']);
        $this->assertCount(0, $props['categories']);
        $this->assertCount(0, $props['justIn']);
    }

    public function test_it_is_open_to_guests_customers_and_admins(): void
    {
        $this->get('/')->assertOk();
        $this->actingAs(User::factory()->create())->get('/')->assertOk();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/')->assertOk();
    }

    public function test_categories_count_only_active_products_and_skip_empty_ones(): void
    {
        $audio = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $empty = Category::create(['name' => 'Empty', 'slug' => 'empty']);
        $hidden = Category::create(['name' => 'Hidden only', 'slug' => 'hidden-only']);
        $this->product($audio);
        $this->product($audio);
        $this->product($audio, ['is_active' => false]);
        $this->product($hidden, ['is_active' => false]);

        $categories = collect($this->props()['categories']);

        $this->assertSame(['audio'], $categories->pluck('slug')->all());
        $this->assertSame(2, $categories->first()['products_count']);
        $this->assertCount(2, $categories->first()['thumbs']);
        $this->assertNotContains($empty->slug, $categories->pluck('slug')->all());
    }

    public function test_the_hero_shows_one_in_stock_product_per_category_and_at_most_four(): void
    {
        foreach (range(1, 6) as $i) {
            $category = Category::create(['name' => "Cat {$i}", 'slug' => "cat-{$i}"]);
            $this->product($category, ['name' => "Item {$i}"]);
            $this->product($category, ['name' => "Second {$i}"]);
        }

        $hero = collect($this->props()['hero']);

        $this->assertCount(4, $hero);
        $this->assertSame($hero->pluck('category')->unique()->count(), $hero->count());
    }

    public function test_the_hero_never_shows_sold_out_hidden_or_photo_less_products(): void
    {
        $category = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $this->product($category, ['name' => 'Sold out', 'stock' => 0]);
        $this->product($category, ['name' => 'Hidden', 'is_active' => false]);
        $this->product($category, ['name' => 'No photo', 'image_path' => null]);
        $shown = $this->product($category, ['name' => 'Visible']);

        $hero = collect($this->props()['hero']);

        $this->assertSame([$shown->id], $hero->pluck('id')->all());
    }

    public function test_just_in_lists_the_newest_in_stock_active_products_only(): void
    {
        $category = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        foreach (range(1, 6) as $i) {
            $this->product($category, ['name' => "Old {$i}", 'created_at' => now()->subDays(10 + $i)]);
        }
        $this->product($category, ['name' => 'Newest sold out', 'stock' => 0, 'created_at' => now()]);
        $this->product($category, ['name' => 'Newest hidden', 'is_active' => false, 'created_at' => now()]);
        $newest = $this->product($category, ['name' => 'Newest', 'created_at' => now()->subMinute()]);

        $names = collect($this->props()['justIn'])->pluck('name');

        $this->assertCount(4, $names);
        $this->assertSame($newest->name, $names->first());
        $this->assertNotContains('Newest sold out', $names->all());
        $this->assertNotContains('Newest hidden', $names->all());
    }

    public function test_the_old_shop_entry_points_still_work_at_the_new_address(): void
    {
        $category = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $this->product($category, ['name' => 'Speaker']);

        $products = $this->get('/shop?category=audio')->viewData('page')['props']['products']['data'];

        $this->assertSame(['Speaker'], array_column($products, 'name'));
    }
}
