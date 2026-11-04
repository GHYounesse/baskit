<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;

beforeEach(function () {
    $this->withoutVite();
    $this->product = Product::factory()->create(['price_cents' => 1500, 'stock' => 10]);
    $this->user = User::factory()->create();
});

function userCart(User $user): Cart
{
    return Cart::firstOrCreate(['user_id' => $user->id]);
}

// ---- Adding ----

it('adds a product to a customer cart', function () {
    $this->actingAs($this->user)->post('/cart', ['product_id' => $this->product->id, 'quantity' => 2])
        ->assertSessionHas('success');

    $item = userCart($this->user)->items->sole();
    expect($item->product_id)->toBe($this->product->id)->and($item->quantity)->toBe(2);
});

it('adds to the quantity instead of duplicating the line', function () {
    $this->actingAs($this->user);

    $this->post('/cart', ['product_id' => $this->product->id, 'quantity' => 2]);
    $this->post('/cart', ['product_id' => $this->product->id, 'quantity' => 3]);

    expect(userCart($this->user)->items)->toHaveCount(1)
        ->and(userCart($this->user)->items->first()->quantity)->toBe(5);
});

it('rejects an invalid product or quantity', function (array $payload) {
    $this->actingAs($this->user)->post('/cart', $payload)->assertSessionHasErrors();

    expect(CartItem::count())->toBe(0);
})->with([
    'unknown product' => [['product_id' => 999999, 'quantity' => 1]],
    'zero quantity' => [fn () => ['product_id' => Product::factory()->create()->id, 'quantity' => 0]],
    'negative quantity' => [fn () => ['product_id' => Product::factory()->create()->id, 'quantity' => -3]],
    'non-numeric quantity' => [fn () => ['product_id' => Product::factory()->create()->id, 'quantity' => 'lots']],
    'missing quantity' => [fn () => ['product_id' => Product::factory()->create()->id]],
]);

it('keeps two variants of the same product as separate lines', function () {
    $small = ProductVariant::factory()->create(['product_id' => $this->product->id, 'size' => 'S', 'color' => null]);
    $large = ProductVariant::factory()->create(['product_id' => $this->product->id, 'size' => 'L', 'color' => null]);
    $this->actingAs($this->user);

    $this->post('/cart', ['product_id' => $this->product->id, 'variant_id' => $small->id, 'quantity' => 1]);
    $this->post('/cart', ['product_id' => $this->product->id, 'variant_id' => $large->id, 'quantity' => 1]);
    $this->post('/cart', ['product_id' => $this->product->id, 'variant_id' => $small->id, 'quantity' => 1]);

    $items = userCart($this->user)->items;
    expect($items)->toHaveCount(2)
        ->and($items->firstWhere('product_variant_id', $small->id)->quantity)->toBe(2)
        ->and($items->firstWhere('product_variant_id', $large->id)->quantity)->toBe(1);
});

it('requires choosing an option for a product that has variants', function () {
    ProductVariant::factory()->create(['product_id' => $this->product->id]);

    $this->actingAs($this->user)->post('/cart', ['product_id' => $this->product->id, 'quantity' => 1])
        ->assertSessionHasErrors('variant_id');

    expect(CartItem::count())->toBe(0);
});

it('refuses a variant that belongs to a different product', function () {
    $foreign = ProductVariant::factory()->create();

    $this->actingAs($this->user)->post('/cart', ['product_id' => $this->product->id, 'variant_id' => $foreign->id, 'quantity' => 1])
        ->assertSessionHasErrors('variant_id');

    expect(CartItem::count())->toBe(0);
});

// ---- Updating and removing ----

it('updates and removes a line in the customer own cart', function () {
    $item = userCart($this->user)->items()->create(['product_id' => $this->product->id, 'quantity' => 1]);
    $this->actingAs($this->user);

    $this->patch("/cart/{$item->id}", ['quantity' => 4])->assertRedirect();
    expect($item->fresh()->quantity)->toBe(4);

    $this->delete("/cart/{$item->id}")->assertRedirect();
    expect(CartItem::count())->toBe(0);
});

it('refuses a quantity below one', function () {
    $item = userCart($this->user)->items()->create(['product_id' => $this->product->id, 'quantity' => 2]);

    $this->actingAs($this->user)->patch("/cart/{$item->id}", ['quantity' => 0])->assertSessionHasErrors('quantity');

    expect($item->fresh()->quantity)->toBe(2);
});

it("forbids touching another customer's cart line", function () {
    $victim = User::factory()->create();
    $item = userCart($victim)->items()->create(['product_id' => $this->product->id, 'quantity' => 2]);
    $this->actingAs($this->user);

    $this->patch("/cart/{$item->id}", ['quantity' => 99])->assertForbidden();
    $this->delete("/cart/{$item->id}")->assertForbidden();

    expect($item->fresh()->quantity)->toBe(2);
});

it("forbids a guest from touching a customer's cart line", function () {
    $item = userCart($this->user)->items()->create(['product_id' => $this->product->id, 'quantity' => 2]);

    $this->patch("/cart/{$item->id}", ['quantity' => 99])->assertForbidden();
    $this->delete("/cart/{$item->id}")->assertForbidden();

    expect(CartItem::count())->toBe(1);
});

// ---- Guests ----

it('keeps a guest cart in the session and counts items', function () {
    $this->post('/cart', ['product_id' => $this->product->id, 'quantity' => 2]);
    $this->post('/cart', ['product_id' => $this->product->id, 'quantity' => 1]);

    $cart = Cart::whereNull('user_id')->sole();
    expect($cart->token)->not->toBeNull()
        ->and($cart->items->sole()->quantity)->toBe(3)
        ->and($cart->items()->sum('quantity'))->toBe(3);
});

it('sums quantities when a guest cart merges into an existing customer cart', function () {
    userCart($this->user)->items()->create(['product_id' => $this->product->id, 'quantity' => 2]);
    $guest = Cart::create(['token' => 'guest-token']);
    $guest->items()->create(['product_id' => $this->product->id, 'quantity' => 3]);
    $other = Product::factory()->create();
    $guest->items()->create(['product_id' => $other->id, 'quantity' => 1]);

    app(CartService::class)->mergeGuestCart($this->user, 'guest-token');

    $items = userCart($this->user)->items;
    expect($items)->toHaveCount(2)
        ->and($items->firstWhere('product_id', $this->product->id)->quantity)->toBe(5)
        ->and(Cart::where('token', 'guest-token')->exists())->toBeFalse();
});

it('ignores an unknown or already-claimed guest token on merge', function () {
    $other = User::factory()->create();
    $claimed = Cart::create(['user_id' => $other->id, 'token' => 'claimed']);
    $claimed->items()->create(['product_id' => $this->product->id, 'quantity' => 1]);

    app(CartService::class)->mergeGuestCart($this->user, 'claimed');
    app(CartService::class)->mergeGuestCart($this->user, 'nope');
    app(CartService::class)->mergeGuestCart($this->user, null);

    expect(userCart($this->user)->items)->toBeEmpty()
        ->and($claimed->fresh()->items)->toHaveCount(1);
});

// ---- Pricing and coupons ----

it('prices the cart from the database, not from the client', function () {
    $cart = userCart($this->user);
    $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 2]);

    $pricing = app(CartService::class)->pricing($cart, $this->user);

    expect($pricing['subtotal_cents'])->toBe(3000)
        ->and($pricing['discount_cents'])->toBe(0)
        ->and($pricing['total_cents'])->toBe(3000);
});

it('applies a valid coupon to the total and removes it again', function () {
    $coupon = Coupon::factory()->percent(10)->create(['code' => 'TEN']);
    userCart($this->user)->items()->create(['product_id' => $this->product->id, 'quantity' => 2]);
    $this->actingAs($this->user);

    $this->post('/cart/coupon', ['code' => 'ten'])->assertSessionHasNoErrors();

    $pricing = app(CartService::class)->pricing(userCart($this->user)->fresh(), $this->user);
    expect(userCart($this->user)->coupon_id)->toBe($coupon->id)
        ->and($pricing['discount_cents'])->toBe(300)
        ->and($pricing['total_cents'])->toBe(2700);

    $this->delete('/cart/coupon');
    expect(userCart($this->user)->fresh()->coupon_id)->toBeNull();
});

it('refuses unknown, expired and inactive coupons', function (callable $make) {
    $code = $make();
    userCart($this->user)->items()->create(['product_id' => $this->product->id, 'quantity' => 1]);

    $this->actingAs($this->user)->post('/cart/coupon', ['code' => $code])->assertSessionHasErrors('code');

    expect(userCart($this->user)->fresh()->coupon_id)->toBeNull();
})->with([
    'unknown' => [fn () => 'NOPE'],
    'expired' => [fn () => Coupon::factory()->expired()->create(['code' => 'OLD'])->code],
    'inactive' => [fn () => Coupon::factory()->create(['code' => 'OFF', 'is_active' => false])->code],
]);

it('refuses a coupon on an empty cart', function () {
    Coupon::factory()->create(['code' => 'TEN']);

    $this->actingAs($this->user)->post('/cart/coupon', ['code' => 'TEN'])->assertSessionHasErrors('code');
});
