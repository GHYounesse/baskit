<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_add_to_cart(): void
    {
        $product = Product::factory()->create();

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();

        $cart = Cart::whereNull('user_id')->firstOrFail();
        $this->assertNotNull($cart->token);
        $this->assertSame(2, (int) $cart->items()->sum('quantity'));
        $this->get('/cart')->assertOk();
    }

    public function test_guest_cannot_touch_another_carts_item(): void
    {
        $product = Product::factory()->create();
        $other = Cart::create(['token' => 'someone-else']);
        $item = $other->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $this->patch("/cart/{$item->id}", ['quantity' => 5])->assertForbidden();
        $this->delete("/cart/{$item->id}")->assertForbidden();
    }

    public function test_guest_cart_merges_into_user_cart_on_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $shared = Product::factory()->create();
        $guestOnly = Product::factory()->create();

        Cart::create(['user_id' => $user->id])->items()->create(['product_id' => $shared->id, 'quantity' => 1]);

        $this->post('/cart', ['product_id' => $shared->id, 'quantity' => 2]);
        $this->post('/cart', ['product_id' => $guestOnly->id, 'quantity' => 1]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

        $this->assertSame(0, Cart::whereNull('user_id')->count());
        $items = Cart::where('user_id', $user->id)->firstOrFail()->items;
        $this->assertSame(3, $items->firstWhere('product_id', $shared->id)->quantity);
        $this->assertSame(1, $items->firstWhere('product_id', $guestOnly->id)->quantity);
    }

    public function test_guest_is_sent_to_login_at_checkout(): void
    {
        $this->get('/checkout')->assertRedirect('/login');
    }

    public function test_prune_command_deletes_only_old_guest_carts(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $old = Cart::create(['token' => 'old']);
        $old->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        Cart::create(['token' => 'fresh']);
        $userCart = Cart::create(['user_id' => $user->id]);

        Cart::whereKey([$old->id, $userCart->id])->update(['updated_at' => now()->subDays(60)]);

        $this->artisan('carts:prune-guests')->assertSuccessful();

        $this->assertDatabaseMissing('carts', ['token' => 'old']);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseHas('carts', ['token' => 'fresh']);
        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
    }
}
