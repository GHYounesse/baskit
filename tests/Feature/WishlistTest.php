<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_log_in(): void
    {
        $product = Product::factory()->create();

        $this->get('/wishlist')->assertRedirect('/login');
        $this->post("/wishlist/{$product->id}")->assertRedirect('/login');
        $this->delete("/wishlist/{$product->id}")->assertRedirect('/login');
    }

    public function test_a_customer_can_save_and_remove_a_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post("/wishlist/{$product->id}")->assertRedirect();
        $this->assertTrue($user->wishlist()->whereKey($product->id)->exists());

        $this->actingAs($user)->delete("/wishlist/{$product->id}");
        $this->assertFalse($user->wishlist()->whereKey($product->id)->exists());
    }

    public function test_saving_twice_keeps_a_single_entry(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post("/wishlist/{$product->id}");
        $this->actingAs($user)->post("/wishlist/{$product->id}");

        $this->assertSame(1, $user->wishlist()->count());
    }

    public function test_removing_something_not_saved_is_harmless(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->delete("/wishlist/{$product->id}")->assertRedirect();
    }

    public function test_inactive_products_cannot_be_saved(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => false]);

        $this->actingAs($user)->post("/wishlist/{$product->id}")->assertNotFound();
    }

    public function test_wishlists_are_private_to_each_customer(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Their secret gift']);
        $this->actingAs($theirs)->post("/wishlist/{$product->id}");

        $page = $this->actingAs($mine)->get('/wishlist');

        $this->assertCount(0, $page->viewData('page')['props']['products']);
        $page->assertDontSee('Their secret gift');
    }

    public function test_the_page_lists_saved_active_products_newest_first(): void
    {
        $user = User::factory()->create();
        $first = Product::factory()->create(['name' => 'First saved']);
        $second = Product::factory()->create(['name' => 'Second saved']);
        $hidden = Product::factory()->create(['name' => 'Later hidden']);

        foreach ([$first, $second, $hidden] as $i => $product) {
            $user->wishlist()->attach($product->id, ['created_at' => now()->addMinutes($i), 'updated_at' => now()]);
        }
        $hidden->update(['is_active' => false]);

        $names = array_column(
            $this->actingAs($user)->get('/wishlist')->viewData('page')['props']['products'],
            'name',
        );

        $this->assertSame(['Second saved', 'First saved'], $names);
    }

    public function test_saved_ids_are_shared_with_every_page(): void
    {
        $user = User::factory()->create();
        $saved = Product::factory()->create();
        Product::factory()->create();
        $user->wishlist()->attach($saved->id);

        $this->assertSame([$saved->id], $this->actingAs($user)->get('/')->viewData('page')['props']['wishlistIds']);
        $this->assertSame([], $this->actingAs(User::factory()->create())->get('/')->viewData('page')['props']['wishlistIds']);
    }

    public function test_deleting_a_product_removes_it_from_wishlists(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $user->wishlist()->attach($product->id);

        $product->delete();

        $this->assertSame(0, $user->wishlist()->count());
    }
}
