<?php

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeStripe;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    $this->customer = User::factory()->create();
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->product = Product::factory()->create(['stock' => 5]);
    $this->order = Order::create([
        'user_id' => $this->customer->id,
        'status' => 'paid',
        'total_cents' => 1000,
        'stripe_payment_intent_id' => 'pi_roles',
    ]);
});

afterEach(fn () => FakeStripe::uninstall());

// ---- Admin area: GET screens ----

dataset('admin screens', [
    'dashboard' => ['/admin'],
    'analytics' => ['/admin/analytics'],
    'inventory' => ['/admin/inventory'],
    'products' => ['/admin/products'],
    'new product' => ['/admin/products/create'],
    'coupons' => ['/admin/coupons'],
    'new coupon' => ['/admin/coupons/create'],
    'orders' => ['/admin/orders'],
]);

it('sends guests to the login page for every admin screen', function (string $url) {
    $this->get($url)->assertRedirect('/login');
})->with('admin screens');

it('forbids customers from every admin screen', function (string $url) {
    $this->actingAs($this->customer)->get($url)->assertForbidden();
})->with('admin screens');

it('lets admins into every admin screen', function (string $url) {
    $this->actingAs($this->admin)->get($url)->assertOk();
})->with('admin screens');

it('lets an admin, but not a customer, open an order in the back office', function () {
    $this->actingAs($this->customer)->get("/admin/orders/{$this->order->id}")->assertForbidden();
    $this->actingAs($this->admin)->get("/admin/orders/{$this->order->id}")->assertOk();
});

// ---- Admin area: write actions ----

it('forbids customers from fulfilling or refunding orders', function () {
    FakeStripe::install();
    $this->actingAs($this->customer);

    $this->post("/admin/orders/{$this->order->id}/ship", ['carrier' => 'UPS'])->assertForbidden();
    $this->post("/admin/orders/{$this->order->id}/deliver")->assertForbidden();
    $this->post("/admin/orders/{$this->order->id}/refund")->assertForbidden();

    expect($this->order->fresh()->status)->toBe('paid');
});

it('forbids customers from changing the catalogue', function () {
    $this->actingAs($this->customer);

    $this->post('/admin/products', ['name' => 'Hack', 'price' => 1, 'stock' => 1])->assertForbidden();
    $this->put("/admin/products/{$this->product->id}", ['name' => 'Hacked'])->assertForbidden();
    $this->delete("/admin/products/{$this->product->id}")->assertForbidden();
    $this->post('/admin/coupons', ['code' => 'FREE', 'type' => 'percent', 'value' => 100])->assertForbidden();

    expect($this->product->fresh())->not->toBeNull()
        ->and($this->product->fresh()->stock)->toBe(5)
        ->and(Coupon::count())->toBe(0);
});

it('forbids guests from writing in the admin area', function () {
    $this->post("/admin/orders/{$this->order->id}/refund")->assertRedirect('/login');
    $this->delete("/admin/products/{$this->product->id}")->assertRedirect('/login');

    expect($this->order->fresh()->status)->toBe('paid')->and($this->product->fresh())->not->toBeNull();
});

it('lets an admin ship an order', function () {
    $this->actingAs($this->admin)->post("/admin/orders/{$this->order->id}/ship", ['tracking_carrier' => 'UPS', 'tracking_number' => '1Z'])
        ->assertSessionHasNoErrors();

    expect($this->order->fresh()->status)->toBe('shipped');
});

// ---- Customer area ----

it('sends guests to login for checkout, orders, profile and wishlist', function (string $url) {
    $this->get($url)->assertRedirect('/login');
})->with(['/checkout', '/orders', '/profile', '/wishlist']);

it('lets a customer see only their own orders', function () {
    $other = User::factory()->create();

    $this->actingAs($this->customer)->get("/orders/{$this->order->id}")->assertOk();
    $this->actingAs($other)->get("/orders/{$this->order->id}")->assertForbidden();
});

it('lets an admin view any customer order through the policy', function () {
    $this->actingAs($this->admin)->get("/orders/{$this->order->id}")->assertOk();
});

it("never lists another customer's orders", function () {
    $other = User::factory()->create();

    $page = $this->actingAs($other)->get('/orders')->assertOk()->viewData('page');

    expect($page['props']['orders'])->toBeEmpty();
});

it('lets only admins through the admin gate and never trusts a client flag', function () {
    expect($this->customer->isAdmin())->toBeFalse()->and($this->admin->isAdmin())->toBeTrue();

    $this->post('/register', [
        'name' => 'Sneaky',
        'email' => 'sneaky@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'is_admin' => 1,
    ]);
    expect(User::where('email', 'sneaky@example.com')->first()?->isAdmin())->toBeFalse();

    $this->actingAs($this->customer)->patch('/profile', [
        'name' => 'Sneaky',
        'email' => $this->customer->email,
        'is_admin' => 1,
    ]);
    expect($this->customer->fresh()->isAdmin())->toBeFalse();
});

it('sends an admin hitting /dashboard to the back office and a customer to the shop', function () {
    $this->actingAs($this->admin)->get('/dashboard')->assertRedirect(route('admin.dashboard'));
    $this->actingAs($this->customer)->get('/dashboard')->assertRedirect(route('shop.index'));
});
