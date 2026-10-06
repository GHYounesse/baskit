<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryAudit;
use App\Services\InventoryService;
use App\Services\OrderLifecycleService;
use App\Services\OrderService;
use App\Services\Payments\PaymentRefunder;
use App\Services\VariantService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Stripe\PaymentIntent;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function product(int $stock = 0, array $attributes = []): Product
    {
        return Product::factory()->create($attributes + ['name' => 'Cotton Tee', 'stock' => $stock, 'price_cents' => 2000, 'category_id' => null]);
    }

    private function variant(Product $product, string $size, string $color, int $stock, ?string $sku = null): ProductVariant
    {
        return app(VariantService::class)->create($product, compact('size', 'color', 'stock', 'sku'));
    }

    private function issues(): array
    {
        return (new InventoryAudit)->issues();
    }

    /** @return list<array{string, int, int}> reason, change, balance */
    private function ledger(?ProductVariant $variant, ?Product $product = null): array
    {
        return StockMovement::query()
            ->when($variant, fn ($q) => $q->where('product_variant_id', $variant->id), fn ($q) => $q->where('product_id', $product->id)->whereNull('product_variant_id'))
            ->orderBy('id')->get()
            ->map(fn (StockMovement $m) => [$m->reason, $m->quantity_change, $m->stock_after])
            ->all();
    }

    // ---- Admin: adding, changing, removing ----

    public function test_the_first_variant_takes_over_stock_tracking_from_the_product(): void
    {
        $product = $this->product(30);

        $variant = $this->variant($product, 'M', 'Blue', 8);

        $this->assertSame(8, $variant->stock);
        $this->assertSame(8, $product->fresh()->stock);   // now just the total of its variants
        // The product's own 30 units were taken out through the ledger, then the variant's 8 came in.
        $this->assertSame([['initial', 30, 30], ['adjustment', -30, 0]], $this->ledger(null, $product));
        $this->assertSame([['initial', 8, 8]], $this->ledger($variant));
        $this->assertSame('Stock moved to variants.', StockMovement::where('reason', 'adjustment')->first()->note);
        $this->assertSame([], $this->issues());
    }

    public function test_a_product_stock_is_the_total_of_its_variants(): void
    {
        $product = $this->product();

        $this->variant($product, 'S', 'Blue', 5);
        $this->variant($product, 'M', 'Blue', 7);
        $this->variant($product, 'L', 'Blue', 0);

        $this->assertSame(12, $product->fresh()->stock);
        $this->assertSame(3, $product->variants()->count());
        $this->assertSame([], $this->issues());
    }

    public function test_variants_come_back_in_the_order_they_were_added(): void
    {
        $product = $this->product();
        $this->variant($product, 'S', '', 1);
        $this->variant($product, 'M', '', 1);
        $this->variant($product, 'L', '', 1);

        $this->assertSame(['S', 'M', 'L'], $product->variants()->pluck('size')->all());
    }

    public function test_a_readable_unique_sku_is_generated_when_none_is_given(): void
    {
        $product = $this->product();

        $first = $this->variant($product, 'M', 'Blue', 1);
        $other = $this->product(0, ['name' => 'Cotton Tee']);   // same name, different product

        $this->assertSame("COTT-{$product->id}-M-BLUE", $first->sku);
        $this->assertSame("COTT-{$other->id}-L", $this->variant($other, 'L', '', 1)->sku);
    }

    public function test_skus_are_stored_upper_case(): void
    {
        $variant = $this->variant($this->product(), 'M', '', 1, '  tee-m-01 ');

        $this->assertSame('TEE-M-01', $variant->sku);
    }

    public function test_admin_can_add_update_and_remove_a_variant(): void
    {
        $admin = $this->admin();
        $product = $this->product();

        $this->actingAs($admin)->post("/admin/products/{$product->id}/variants", [
            'size' => 'M', 'color' => 'Blue', 'sku' => 'tee-m-blue', 'stock' => 6,
        ])->assertSessionHasNoErrors();

        $variant = ProductVariant::firstOrFail();
        $this->assertSame('TEE-M-BLUE', $variant->sku);
        $this->assertSame(6, $product->fresh()->stock);

        $this->actingAs($admin)->put("/admin/variants/{$variant->id}", [
            'size' => 'M', 'color' => 'Navy', 'sku' => 'TEE-M-NAVY', 'stock' => 9, 'stock_note' => 'Delivery',
        ])->assertSessionHasNoErrors();

        $variant->refresh();
        $this->assertSame('Navy', $variant->color);
        $this->assertSame(9, $variant->stock);
        $this->assertSame(9, $product->fresh()->stock);
        $movement = StockMovement::latest('id')->first();
        $this->assertSame(['adjustment', 3, 9, 'Delivery', $admin->id], [$movement->reason, $movement->quantity_change, $movement->stock_after, $movement->note, $movement->user_id]);
        $this->assertSame('M / Navy', $movement->variant_label);

        $this->actingAs($admin)->delete("/admin/variants/{$variant->id}")->assertSessionHasNoErrors();

        $this->assertModelMissing($variant);
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame([], $this->issues());
    }

    public function test_saving_a_variant_without_changing_its_stock_logs_nothing(): void
    {
        $variant = $this->variant($this->product(), 'M', 'Blue', 5);

        $this->actingAs($this->admin())->put("/admin/variants/{$variant->id}", [
            'size' => 'M', 'color' => 'Blue', 'sku' => $variant->sku, 'stock' => 5,
        ])->assertSessionHasNoErrors();

        $this->assertSame([['initial', 5, 5]], $this->ledger($variant));
    }

    public function test_removing_a_variant_takes_its_stock_out_through_the_ledger_and_keeps_history(): void
    {
        $product = $this->product();
        $keep = $this->variant($product, 'S', 'Blue', 4);
        $drop = $this->variant($product, 'M', 'Blue', 6);

        app(VariantService::class)->delete($drop);

        $this->assertSame(4, $product->fresh()->stock);
        $history = StockMovement::where('product_variant_id', $drop->id)->orderBy('id')->get()
            ->map(fn (StockMovement $m) => [$m->reason, $m->quantity_change, $m->stock_after])->all();
        $this->assertSame([['initial', 6, 6], ['adjustment', -6, 0]], $history);
        $this->assertSame(1, StockMovement::where('variant_label', 'M / Blue')->where('quantity_change', -6)->count());
        $this->assertTrue($keep->exists);
        $this->assertSame([], $this->issues());   // no false alarm from the deleted variant's history
    }

    public function test_removing_the_last_variant_returns_the_product_to_a_simple_one(): void
    {
        $product = $this->product();
        $variant = $this->variant($product, 'M', 'Blue', 3);

        app(VariantService::class)->delete($variant);

        $this->assertFalse($product->fresh()->hasVariants());
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame([], $this->issues());

        // ...and it can be stocked directly again.
        app(InventoryService::class)->setStock($product->fresh(), 12);
        $this->assertSame([], $this->issues());
    }

    public function test_a_variant_needs_an_option_and_no_two_may_match(): void
    {
        $admin = $this->admin();
        $product = $this->product();
        $this->variant($product, 'M', 'Blue', 1);
        $post = fn (array $data) => $this->actingAs($admin)->post("/admin/products/{$product->id}/variants", $data + ['stock' => 1]);

        $post(['size' => '', 'color' => ''])->assertSessionHasErrors('size');
        $post(['size' => 'm', 'color' => ' blue '])->assertSessionHasErrors('size');   // same options, different case
        $post(['size' => 'M', 'color' => 'Red'])->assertSessionHasNoErrors();           // same size, new colour is fine
        $post(['size' => '', 'color' => 'Green'])->assertSessionHasNoErrors();          // colour only
        $post(['size' => 'XL', 'color' => ''])->assertSessionHasNoErrors();             // size only
        $this->assertSame(4, $product->variants()->count());
    }

    public function test_the_same_options_are_fine_on_a_different_product(): void
    {
        $this->variant($this->product(), 'M', 'Blue', 1);
        $other = $this->product(0, ['name' => 'Another']);

        $this->actingAs($this->admin())->post("/admin/products/{$other->id}/variants", ['size' => 'M', 'color' => 'Blue', 'stock' => 1])
            ->assertSessionHasNoErrors();
    }

    public function test_skus_must_be_unique_and_well_formed(): void
    {
        $admin = $this->admin();
        $product = $this->product();
        $this->variant($product, 'S', '', 1, 'TEE-S');
        $post = fn (array $data) => $this->actingAs($admin)->post("/admin/products/{$product->id}/variants", $data + ['size' => 'M', 'stock' => 1]);

        $post(['sku' => 'tee-s'])->assertSessionHasErrors('sku');          // taken (case-insensitive)
        $post(['sku' => 'has spaces!'])->assertSessionHasErrors('sku');
        $post(['sku' => 'TEE-M'])->assertSessionHasNoErrors();
    }

    public function test_a_variant_can_keep_its_own_sku_and_options_when_edited(): void
    {
        $variant = $this->variant($this->product(), 'M', 'Blue', 5, 'TEE-M');

        $this->actingAs($this->admin())->put("/admin/variants/{$variant->id}", [
            'size' => 'M', 'color' => 'Blue', 'sku' => 'tee-m', 'stock' => 7,
        ])->assertSessionHasNoErrors();

        $this->assertSame(7, $variant->fresh()->stock);
    }

    public function test_stock_must_be_a_whole_number_of_zero_or_more(): void
    {
        $product = $this->product();

        foreach (['-1', 'abc', '2.5', ''] as $bad) {
            $this->actingAs($this->admin())->post("/admin/products/{$product->id}/variants", ['size' => 'M', 'stock' => $bad])
                ->assertSessionHasErrors('stock');
        }
    }

    public function test_only_admins_can_manage_variants(): void
    {
        $product = $this->product();
        $variant = $this->variant($product, 'M', '', 1);
        $customer = User::factory()->create();

        $this->post("/admin/products/{$product->id}/variants", ['size' => 'L', 'stock' => 1])->assertRedirect('/login');
        $this->actingAs($customer)->post("/admin/products/{$product->id}/variants", ['size' => 'L', 'stock' => 1])->assertForbidden();
        $this->actingAs($customer)->put("/admin/variants/{$variant->id}", ['size' => 'L', 'stock' => 1])->assertForbidden();
        $this->actingAs($customer)->delete("/admin/variants/{$variant->id}")->assertForbidden();
        $this->assertSame(1, ProductVariant::count());
    }

    public function test_the_products_own_stock_field_is_ignored_once_it_has_variants(): void
    {
        $product = $this->product();
        $this->variant($product, 'M', '', 5);

        $this->actingAs($this->admin())->put("/admin/products/{$product->id}", [
            'name' => 'Renamed', 'price_cents' => 2500, 'stock' => 999, 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $product->fresh()->name);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame([], $this->issues());
    }

    public function test_the_edit_page_and_list_carry_the_variants(): void
    {
        $product = $this->product();
        $this->variant($product, 'M', 'Blue', 3);
        $this->variant($product, 'L', 'Blue', 2);
        $admin = $this->admin();

        $edit = $this->actingAs($admin)->get("/admin/products/{$product->id}/edit")->assertOk()->viewData('page')['props'];
        $this->assertSame(['M / Blue', 'L / Blue'], array_column($edit['variants'], 'label'));

        $list = $this->actingAs($admin)->get('/admin/products')->assertOk()->viewData('page')['props'];
        $this->assertSame(2, $list['products']['data'][0]['variants_count']);
    }

    // ---- The service guards the invariant ----

    public function test_stock_cannot_be_changed_on_a_product_that_has_variants(): void
    {
        $product = $this->product();
        $this->variant($product, 'M', '', 5);

        $this->expectException(LogicException::class);
        app(InventoryService::class)->adjust($product, 1, StockMovement::ADJUSTMENT);
    }

    public function test_setting_a_products_stock_directly_is_refused_when_it_has_variants(): void
    {
        $product = $this->product();
        $this->variant($product, 'M', '', 5);

        $this->expectException(LogicException::class);
        app(InventoryService::class)->setStock($product, 50);
    }

    public function test_a_variant_of_another_product_cannot_be_adjusted(): void
    {
        $mine = $this->product();
        $other = $this->variant($this->product(0, ['name' => 'Other']), 'M', '', 5);

        $this->expectException(ModelNotFoundException::class);
        app(InventoryService::class)->adjust($mine, 1, StockMovement::ADJUSTMENT, variant: $other);
    }

    public function test_a_variant_cannot_go_below_zero_even_when_the_product_total_could_cover_it(): void
    {
        $product = $this->product();
        $empty = $this->variant($product, 'S', '', 0);
        $this->variant($product, 'M', '', 50);

        try {
            app(InventoryService::class)->adjust($product, -1, StockMovement::SALE, variant: $empty);
            $this->fail('Expected the sale to be refused');
        } catch (InsufficientStockException $e) {
            $this->assertStringContainsString('Cotton Tee (S)', $e->getMessage());
        }

        $this->assertSame(50, $product->fresh()->stock);
        $this->assertSame([], $this->issues());
    }

    // ---- Cart ----

    private function addToCart(array $data)
    {
        return $this->post('/cart', $data + ['quantity' => 1]);
    }

    public function test_a_product_with_variants_cannot_be_added_without_choosing_one(): void
    {
        $product = $this->product();
        $this->variant($product, 'M', 'Blue', 5);

        $this->addToCart(['product_id' => $product->id])->assertSessionHasErrors('variant_id');

        $this->assertSame(0, CartItem::count());
    }

    public function test_a_variant_of_another_product_is_rejected(): void
    {
        $product = $this->product();
        $this->variant($product, 'M', '', 5);
        $otherVariant = $this->variant($this->product(0, ['name' => 'Other']), 'M', '', 5);

        $this->addToCart(['product_id' => $product->id, 'variant_id' => $otherVariant->id])->assertSessionHasErrors('variant_id');
    }

    public function test_a_simple_product_cannot_be_given_a_variant(): void
    {
        $simple = $this->product(5);
        $variant = $this->variant($this->product(0, ['name' => 'Other']), 'M', '', 5);

        $this->addToCart(['product_id' => $simple->id, 'variant_id' => $variant->id])->assertSessionHasErrors('variant_id');
        $this->addToCart(['product_id' => $simple->id])->assertSessionHasNoErrors();
    }

    public function test_the_same_variant_adds_to_one_line_and_different_ones_get_their_own(): void
    {
        $product = $this->product();
        $medium = $this->variant($product, 'M', 'Blue', 5);
        $large = $this->variant($product, 'L', 'Blue', 5);

        $this->addToCart(['product_id' => $product->id, 'variant_id' => $medium->id]);
        $this->addToCart(['product_id' => $product->id, 'variant_id' => $medium->id, 'quantity' => 2]);
        $this->addToCart(['product_id' => $product->id, 'variant_id' => $large->id]);

        $items = Cart::firstOrFail()->items()->orderBy('id')->get();
        $this->assertCount(2, $items);
        $this->assertSame([$medium->id, 3], [$items[0]->product_variant_id, $items[0]->quantity]);
        $this->assertSame([$large->id, 1], [$items[1]->product_variant_id, $items[1]->quantity]);
    }

    public function test_the_cart_page_shows_which_variant_each_line_is(): void
    {
        $product = $this->product();
        $variant = $this->variant($product, 'M', 'Blue', 5);
        $this->addToCart(['product_id' => $product->id, 'variant_id' => $variant->id]);

        $props = $this->get('/cart')->assertOk()->viewData('page')['props'];

        $this->assertSame('M / Blue', $props['cart']['items'][0]['variant']['label']);
        $this->assertSame(2000, $props['pricing']['subtotal_cents']);   // variants share the product's price
    }

    public function test_a_guests_variants_follow_them_into_their_account_cart(): void
    {
        $product = $this->product();
        $variant = $this->variant($product, 'M', 'Blue', 5);
        $user = User::factory()->create();
        Cart::create(['user_id' => $user->id])->items()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->addToCart(['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $items = Cart::where('user_id', $user->id)->firstOrFail()->items;
        $this->assertCount(1, $items);
        $this->assertSame(3, $items[0]->quantity);
        $this->assertSame($variant->id, $items[0]->product_variant_id);
    }

    // ---- Orders and refunds ----

    private function intent(User $user, int $amount, string $id = 'pi_var_1'): PaymentIntent
    {
        return PaymentIntent::constructFrom([
            'id' => $id, 'status' => 'succeeded', 'amount_received' => $amount, 'metadata' => ['user_id' => $user->id],
        ]);
    }

    private function cartWith(User $user, array $lines): void
    {
        $cart = Cart::create(['user_id' => $user->id]);
        foreach ($lines as [$product, $variant, $quantity]) {
            $cart->items()->create(['product_id' => $product->id, 'product_variant_id' => $variant?->id, 'quantity' => $quantity]);
        }
    }

    public function test_an_order_takes_stock_from_the_chosen_variant_and_remembers_it(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $product = $this->product();
        $medium = $this->variant($product, 'M', 'Blue', 5);
        $large = $this->variant($product, 'L', 'Blue', 5);
        $this->cartWith($user, [[$product, $medium, 2], [$product, $large, 1]]);

        $order = app(OrderService::class)->createFromPaymentIntent($this->intent($user, 6000));

        $this->assertSame(3, $medium->fresh()->stock);
        $this->assertSame(4, $large->fresh()->stock);
        $this->assertSame(7, $product->fresh()->stock);

        $line = $order->items()->where('product_variant_id', $medium->id)->firstOrFail();
        $this->assertSame(['M / Blue', $medium->sku, 'Cotton Tee (M / Blue)'], [$line->variant_label, $line->sku, $line->display_name]);

        $sale = StockMovement::where('product_variant_id', $medium->id)->where('reason', 'sale')->firstOrFail();
        $this->assertSame([-2, 3, 'M / Blue', $order->id, $user->id], [$sale->quantity_change, $sale->stock_after, $sale->variant_label, $sale->order_id, $sale->user_id]);
        $this->assertSame([], $this->issues());
    }

    public function test_a_retried_payment_for_a_variant_order_is_taken_once(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $product = $this->product();
        $variant = $this->variant($product, 'M', '', 5);
        $this->cartWith($user, [[$product, $variant, 2]]);
        $service = app(OrderService::class);

        $service->createFromPaymentIntent($this->intent($user, 4000));
        $service->createFromPaymentIntent($this->intent($user, 4000));

        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertSame(1, Order::count());
    }

    public function test_an_order_is_refused_when_the_chosen_variant_is_sold_out_even_if_others_have_stock(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $soldOut = $this->variant($product, 'S', '', 1);
        $this->variant($product, 'M', '', 50);
        $this->cartWith($user, [[$product, $soldOut, 2]]);

        $this->expectException(InsufficientStockException::class);
        $this->expectExceptionMessage('Cotton Tee (S)');
        app(OrderService::class)->createFromPaymentIntent($this->intent($user, 4000));
    }

    public function test_simple_and_variant_products_can_share_an_order(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $tee = $this->product();
        $variant = $this->variant($tee, 'M', '', 5);
        $mug = $this->product(10, ['name' => 'Mug', 'price_cents' => 800]);
        $this->cartWith($user, [[$tee, $variant, 1], [$mug, null, 2]]);

        $order = app(OrderService::class)->createFromPaymentIntent($this->intent($user, 3600));

        $this->assertSame(4, $variant->fresh()->stock);
        $this->assertSame(8, $mug->fresh()->stock);
        $this->assertNull($order->items()->where('product_id', $mug->id)->first()->variant_label);
        $this->assertSame([], $this->issues());
    }

    public function test_a_refund_puts_the_units_back_on_the_same_variant(): void
    {
        Mail::fake();
        $this->app->instance(PaymentRefunder::class, new class implements PaymentRefunder
        {
            public function refund(string $paymentIntentId, string $idempotencyKey): ?string
            {
                return 're_test';
            }
        });
        $user = User::factory()->create();
        $product = $this->product();
        $variant = $this->variant($product, 'M', '', 5);
        $this->cartWith($user, [[$product, $variant, 2]]);
        $order = app(OrderService::class)->createFromPaymentIntent($this->intent($user, 4000));

        app(OrderLifecycleService::class)->refund($order, $this->admin());

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame([['initial', 5, 5], ['sale', -2, 3], ['return', 2, 5]], $this->ledger($variant));
        $this->assertSame([], $this->issues());
    }

    public function test_refunding_an_order_for_a_since_deleted_variant_does_not_break(): void
    {
        Mail::fake();
        $this->app->instance(PaymentRefunder::class, new class implements PaymentRefunder
        {
            public function refund(string $paymentIntentId, string $idempotencyKey): ?string
            {
                return 're_test';
            }
        });
        $user = User::factory()->create();
        $product = $this->product();
        $gone = $this->variant($product, 'S', '', 5);
        $this->variant($product, 'M', '', 5);
        $this->cartWith($user, [[$product, $gone, 2]]);
        $order = app(OrderService::class)->createFromPaymentIntent($this->intent($user, 4000));
        app(VariantService::class)->delete($gone);

        app(OrderLifecycleService::class)->refund($order, $this->admin());

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);   // only the surviving variant's stock
        $this->assertSame([], $this->issues());
    }

    // ---- Integrity check at the variant level ----

    public function test_variant_stock_changed_behind_the_apps_back_is_reported(): void
    {
        $product = $this->product();
        $variant = $this->variant($product, 'M', 'Blue', 5);

        DB::table('product_variants')->where('id', $variant->id)->update(['stock' => 99]);

        $issues = $this->issues();
        $drift = collect($issues)->firstWhere('product_name', 'Cotton Tee (M / Blue)');
        $this->assertSame('drift', $drift['type']);
        $this->assertStringContainsString('Stock is 99 but the history adds up to 5', $drift['detail']);
    }

    public function test_a_variant_ledger_entry_that_was_edited_breaks_the_chain(): void
    {
        $product = $this->product();
        $variant = $this->variant($product, 'M', '', 10);
        app(InventoryService::class)->adjust($product, -2, StockMovement::SALE, variant: $variant);
        app(InventoryService::class)->adjust($product, -3, StockMovement::SALE, variant: $variant);

        StockMovement::where('product_variant_id', $variant->id)->where('reason', 'sale')->orderBy('id')->first()->forceFill(['stock_after' => 9])->save();

        $issues = $this->issues();
        $this->assertSame(['broken chain'], array_column($issues, 'type'));
        $this->assertSame('Cotton Tee (M)', $issues[0]['product_name']);
    }

    public function test_a_product_total_that_no_longer_matches_its_variants_is_reported(): void
    {
        $product = $this->product();
        $this->variant($product, 'M', '', 5);

        DB::table('products')->where('id', $product->id)->update(['stock' => 40]);

        $this->assertSame(['drift'], array_column($this->issues(), 'type'));
    }

    // ---- The storefront ----

    public function test_the_product_page_lists_its_variants(): void
    {
        $product = $this->product();
        $this->variant($product, 'S', 'Blue', 0);
        $this->variant($product, 'M', 'Blue', 4);

        $variants = $this->get("/products/{$product->slug}")->assertOk()->viewData('page')['props']['product']['variants'];

        $this->assertSame(['S / Blue', 'M / Blue'], array_column($variants, 'label'));
        $this->assertSame([0, 4], array_column($variants, 'stock'));
        $this->assertArrayHasKey('sku', $variants[0]);
    }

    public function test_the_wishlist_knows_which_products_need_an_option_chosen(): void
    {
        $user = User::factory()->create();
        $withVariants = $this->product();
        $this->variant($withVariants, 'M', '', 1);
        $simple = $this->product(3, ['name' => 'Simple']);
        $user->wishlist()->attach([$withVariants->id, $simple->id]);

        $counts = collect($this->actingAs($user)->get('/wishlist')->viewData('page')['props']['products'])
            ->pluck('variants_count', 'name');

        $this->assertSame(1, $counts['Cotton Tee']);
        $this->assertSame(0, $counts['Simple']);
    }

    public function test_deleting_a_product_removes_its_variants_but_keeps_the_history(): void
    {
        $product = $this->product();
        $variant = $this->variant($product, 'M', 'Blue', 5);

        $product->delete();

        $this->assertSame(0, ProductVariant::count());
        $this->assertSame(1, StockMovement::where('product_variant_id', $variant->id)->count());
        $this->assertSame([], $this->issues());
    }
}
