<?php

use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderLifecycleService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Mail;
use Stripe\PaymentIntent;
use Tests\Support\FakeStripe;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
});

afterEach(fn () => FakeStripe::uninstall());

function intentFor(User $user, int $amount, string $id): PaymentIntent
{
    return PaymentIntent::constructFrom([
        'id' => $id,
        'status' => 'succeeded',
        'amount_received' => $amount,
        'metadata' => ['user_id' => $user->id],
    ]);
}

/**
 * The variant factory doesn't touch the product, but in the app a product's stock is the total of its variants'.
 */
function withVariantTotal(Product $product): Product
{
    $product->forceFill(['stock' => $product->variants()->sum('stock')])->saveQuietly();

    return $product;
}

function cartWith(User $user, Product $product, int $quantity, ?ProductVariant $variant = null): void
{
    Cart::firstOrCreate(['user_id' => $user->id])->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant?->id,
        'quantity' => $quantity,
    ]);
}

it('sells the last unit to one buyer only; the second order is refused and stock stays at zero', function () {
    $product = Product::factory()->create(['price_cents' => 1000, 'stock' => 1]);
    [$first, $second] = User::factory()->count(2)->create();
    cartWith($first, $product, 1);
    cartWith($second, $product, 1);
    $orders = app(OrderService::class);

    $orders->createFromPaymentIntent(intentFor($first, 1000, 'pi_first'));

    expect(fn () => $orders->createFromPaymentIntent(intentFor($second, 1000, 'pi_second')))
        ->toThrow(InsufficientStockException::class);

    expect(Order::count())->toBe(1)
        ->and($product->fresh()->stock)->toBe(0)
        ->and(Order::sole()->user_id)->toBe($first->id);
});

it('rolls the whole order back when one of several lines is short', function () {
    $plenty = Product::factory()->create(['price_cents' => 1000, 'stock' => 10]);
    $scarce = Product::factory()->create(['price_cents' => 1000, 'stock' => 1]);
    $user = User::factory()->create();
    cartWith($user, $plenty, 2);
    cartWith($user, $scarce, 2);

    expect(fn () => app(OrderService::class)->createFromPaymentIntent(intentFor($user, 4000, 'pi_partial')))
        ->toThrow(InsufficientStockException::class);

    expect(Order::count())->toBe(0)
        ->and($plenty->fresh()->stock)->toBe(10)
        ->and($scarce->fresh()->stock)->toBe(1)
        ->and(StockMovement::where('reason', StockMovement::SALE)->count())->toBe(0)
        ->and(Cart::where('user_id', $user->id)->first()->items)->toHaveCount(2);
});

it('takes stock from the chosen variant and keeps the product total in step', function () {
    $product = Product::factory()->create(['price_cents' => 1000, 'stock' => 0]);
    $small = ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 'S', 'color' => null, 'stock' => 4]);
    $large = ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 'L', 'color' => null, 'stock' => 6]);
    withVariantTotal($product);
    $user = User::factory()->create();
    cartWith($user, $product, 3, $small);

    $order = app(OrderService::class)->createFromPaymentIntent(intentFor($user, 3000, 'pi_variant'));

    $movement = StockMovement::where('reason', StockMovement::SALE)->sole();
    expect($small->fresh()->stock)->toBe(1)
        ->and($large->fresh()->stock)->toBe(6)
        ->and($product->fresh()->stock)->toBe($small->fresh()->stock + $large->fresh()->stock)
        ->and($order->items->sole()->variant_label)->toBe('S')
        ->and($movement->quantity_change)->toBe(-3)
        ->and($movement->stock_after)->toBe(1)
        ->and($movement->product_variant_id)->toBe($small->id)
        ->and($movement->order_id)->toBe($order->id);
});

it('refuses to oversell a variant even when the product total would cover it', function () {
    $product = Product::factory()->create(['price_cents' => 1000, 'stock' => 0]);
    $small = ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 'S', 'color' => null, 'stock' => 1]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 'L', 'color' => null, 'stock' => 20]);
    $user = User::factory()->create();
    cartWith($user, $product, 2, $small);

    expect(fn () => app(OrderService::class)->createFromPaymentIntent(intentFor($user, 2000, 'pi_oversell')))
        ->toThrow(InsufficientStockException::class);

    expect($small->fresh()->stock)->toBe(1)->and(Order::count())->toBe(0);
});

it('blocks payment up front when a variant is short', function () {
    FakeStripe::install();
    $product = Product::factory()->create(['price_cents' => 1000, 'stock' => 0]);
    $small = ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 'S', 'color' => null, 'stock' => 1]);
    $user = User::factory()->create();
    cartWith($user, $product, 2, $small);

    $this->actingAs($user)->postJson('/checkout/payment-intent')->assertStatus(422);
});

it('returns the stock of a cancelled order, variant included, and logs it as a return', function () {
    $product = Product::factory()->create(['price_cents' => 1000, 'stock' => 0]);
    $small = ProductVariant::factory()->create(['product_id' => $product->id, 'size' => 'S', 'color' => null, 'stock' => 4]);
    withVariantTotal($product);
    $user = User::factory()->create();
    cartWith($user, $product, 3, $small);
    $order = app(OrderService::class)->createFromPaymentIntent(intentFor($user, 3000, 'pi_cancel'));
    FakeStripe::install();

    app(OrderLifecycleService::class)->refund($order, $user, byCustomer: true);

    expect($small->fresh()->stock)->toBe(4)
        ->and($product->fresh()->stock)->toBe(4)
        ->and(StockMovement::where('reason', StockMovement::RETURN)->sole()->quantity_change)->toBe(3);
});

it('never lets stock go negative and logs nothing for a refused change', function () {
    $product = Product::factory()->create(['stock' => 2]);
    $inventory = app(InventoryService::class);
    $before = StockMovement::count();

    expect(fn () => $inventory->adjust($product, -3, StockMovement::ADJUSTMENT))
        ->toThrow(InsufficientStockException::class);

    expect($product->fresh()->stock)->toBe(2)->and(StockMovement::count())->toBe($before);
});

it('refuses to change a variant product stock without naming the variant', function () {
    $product = Product::factory()->create(['stock' => 0]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 5]);

    expect(fn () => app(InventoryService::class)->adjust($product, 1, StockMovement::ADJUSTMENT))
        ->toThrow(LogicException::class);
});

it('keeps the ledger equal to the stock after a sale and a return', function () {
    $product = Product::factory()->create(['price_cents' => 1000, 'stock' => 0]);
    $inventory = app(InventoryService::class);
    $inventory->adjust($product, 10, StockMovement::ADJUSTMENT);
    $user = User::factory()->create();
    cartWith($user, $product, 4);
    $order = app(OrderService::class)->createFromPaymentIntent(intentFor($user, 4000, 'pi_ledger'));
    FakeStripe::install();
    app(OrderLifecycleService::class)->refund($order, $user);

    $movements = StockMovement::where('product_id', $product->id)->orderBy('id')->get();
    expect($movements->sum('quantity_change'))->toBe($product->fresh()->stock)
        ->and($product->fresh()->stock)->toBe(10)
        ->and($movements->last()->stock_after)->toBe(10);
});
