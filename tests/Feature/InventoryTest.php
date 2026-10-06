<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryAudit;
use App\Services\InventoryService;
use App\Services\OrderLifecycleService;
use App\Services\OrderService;
use App\Services\Payments\PaymentRefunder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Stripe\PaymentIntent;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function product(int $stock = 10, array $attributes = []): Product
    {
        return Product::factory()->create($attributes + ['stock' => $stock, 'price_cents' => 1000, 'category_id' => null]);
    }

    /** @return list<array{string, int, int}> reason, change, balance for a product, oldest first */
    private function ledger(Product $product): array
    {
        return StockMovement::where('product_id', $product->id)->orderBy('id')->get()
            ->map(fn (StockMovement $m) => [$m->reason, $m->quantity_change, $m->stock_after])
            ->all();
    }

    private function inventory(): InventoryService
    {
        return app(InventoryService::class);
    }

    // ---- Creating products ----

    public function test_a_new_product_with_stock_gets_an_opening_entry(): void
    {
        $product = $this->product(25);

        $this->assertSame([['initial', 25, 25]], $this->ledger($product));
        $this->assertSame($product->name, StockMovement::first()->product_name);
    }

    public function test_a_product_created_with_no_stock_has_no_entry_yet(): void
    {
        $product = $this->product(0);

        $this->assertSame([], $this->ledger($product));
    }

    public function test_an_admin_creating_a_product_is_recorded_as_the_actor(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/products', [
            'name' => 'New thing', 'price_cents' => 500, 'stock' => 12, 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $movement = StockMovement::where('product_name', 'New thing')->firstOrFail();
        $this->assertSame('initial', $movement->reason);
        $this->assertSame(12, $movement->stock_after);
        $this->assertSame($admin->id, $movement->user_id);
    }

    // ---- The service ----

    public function test_adjusting_changes_stock_and_logs_the_running_balance(): void
    {
        $product = $this->product(10);

        $this->inventory()->adjust($product, -3, StockMovement::SALE);
        $this->inventory()->adjust($product, 5, StockMovement::RETURN, note: ' Customer returned two ');

        $this->assertSame(12, $product->fresh()->stock);
        $this->assertSame([['initial', 10, 10], ['sale', -3, 7], ['return', 5, 12]], $this->ledger($product));
        $this->assertSame('Customer returned two', StockMovement::latest('id')->first()->note);
    }

    public function test_the_instance_the_caller_holds_is_kept_up_to_date(): void
    {
        $product = $this->product(10);

        $this->inventory()->adjust($product, -4, StockMovement::SALE);

        $this->assertSame(6, $product->stock);
        $this->assertFalse($product->isDirty('stock'));
    }

    public function test_stock_can_never_go_below_zero_and_nothing_is_logged_for_a_refused_change(): void
    {
        $product = $this->product(2);

        try {
            $this->inventory()->adjust($product, -3, StockMovement::SALE);
            $this->fail('Expected the change to be refused');
        } catch (InsufficientStockException) {
            // expected
        }

        $this->assertSame(2, $product->fresh()->stock);
        $this->assertSame([['initial', 2, 2]], $this->ledger($product));
    }

    public function test_setting_stock_logs_the_difference_from_what_is_on_hand_now(): void
    {
        $product = $this->product(10);
        $admin = $this->admin();

        $movement = $this->inventory()->setStock($product, 15, $admin, 'Recount after delivery');

        $this->assertSame(5, $movement->quantity_change);
        $this->assertSame(15, $movement->stock_after);
        $this->assertSame('adjustment', $movement->reason);
        $this->assertSame($admin->id, $movement->user_id);
        $this->assertSame('Recount after delivery', $movement->note);
    }

    public function test_setting_stock_to_the_same_number_logs_nothing(): void
    {
        $product = $this->product(10);

        $this->assertNull($this->inventory()->setStock($product, 10));
        $this->assertSame([['initial', 10, 10]], $this->ledger($product));
    }

    public function test_setting_stock_works_from_the_live_stock_not_a_stale_copy(): void
    {
        $product = $this->product(10);
        $stale = Product::find($product->id);                          // an admin opens the form: stock 10

        $this->inventory()->adjust($product, -4, StockMovement::SALE); // an order lands: stock 6

        $this->inventory()->setStock($stale, 20);                      // the admin saves "20"

        $this->assertSame(20, $product->fresh()->stock);
        $this->assertSame([['initial', 10, 10], ['sale', -4, 6], ['adjustment', 14, 20]], $this->ledger($product));
        $this->assertSame([], (new InventoryAudit)->issues());
    }

    // ---- Orders and refunds ----

    private function intent(User $user, int $amount, string $id = 'pi_inv_1'): PaymentIntent
    {
        return PaymentIntent::constructFrom([
            'id' => $id, 'status' => 'succeeded', 'amount_received' => $amount, 'metadata' => ['user_id' => $user->id],
        ]);
    }

    public function test_a_sale_is_logged_against_the_order_and_the_customer(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $first = $this->product(5);
        $second = $this->product(3);
        $cart = Cart::create(['user_id' => $user->id]);
        $cart->items()->create(['product_id' => $first->id, 'quantity' => 2]);
        $cart->items()->create(['product_id' => $second->id, 'quantity' => 1]);

        $order = app(OrderService::class)->createFromPaymentIntent($this->intent($user, 3000));

        $sale = StockMovement::where('product_id', $first->id)->where('reason', 'sale')->firstOrFail();
        $this->assertSame(-2, $sale->quantity_change);
        $this->assertSame(3, $sale->stock_after);
        $this->assertSame($order->id, $sale->order_id);
        $this->assertSame($user->id, $sale->user_id);
        $this->assertSame([['initial', 3, 3], ['sale', -1, 2]], $this->ledger($second));
        $this->assertSame([], (new InventoryAudit)->issues());
    }

    public function test_a_retried_payment_is_logged_once(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $product = $this->product(5);
        Cart::create(['user_id' => $user->id])->items()->create(['product_id' => $product->id, 'quantity' => 2]);
        $service = app(OrderService::class);

        $service->createFromPaymentIntent($this->intent($user, 2000));
        $service->createFromPaymentIntent($this->intent($user, 2000));

        $this->assertSame([['initial', 5, 5], ['sale', -2, 3]], $this->ledger($product));
    }

    public function test_a_refund_with_restock_logs_a_return_and_one_without_does_not(): void
    {
        Mail::fake();
        $this->app->instance(PaymentRefunder::class, new class implements PaymentRefunder
        {
            public function refund(string $paymentIntentId, string $idempotencyKey): ?string
            {
                return 're_test';
            }
        });
        $admin = $this->admin();
        $product = $this->product(10);

        foreach ([true, false] as $restock) {
            $order = Order::create(['user_id' => User::factory()->create()->id, 'status' => 'paid', 'total_cents' => 2000, 'stripe_payment_intent_id' => 'pi_'.uniqid()]);
            $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'unit_price_cents' => 1000, 'quantity' => 2]);
            $this->inventory()->adjust($product, -2, StockMovement::SALE, $order);

            app(OrderLifecycleService::class)->refund($order, $admin, restock: $restock);
        }

        // Two sales of 2; only the first refund returned its units.
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame([['initial', 10, 10], ['sale', -2, 8], ['return', 2, 10], ['sale', -2, 8]], $this->ledger($product));
        $returned = StockMovement::where('reason', 'return')->firstOrFail();
        $this->assertSame($admin->id, $returned->user_id);
        $this->assertNotNull($returned->order_id);
        $this->assertSame([], (new InventoryAudit)->issues());
    }

    // ---- Safety net and history ----

    public function test_a_stock_change_made_with_a_plain_save_is_still_recorded(): void
    {
        $product = $this->product(10);
        $admin = $this->admin();
        $this->actingAs($admin);

        $product->stockNote = 'Fixed in tinker';
        $product->update(['stock' => 7]);

        $this->assertSame([['initial', 10, 10], ['adjustment', -3, 7]], $this->ledger($product));
        $movement = StockMovement::latest('id')->first();
        $this->assertSame('Fixed in tinker', $movement->note);
        $this->assertSame($admin->id, $movement->user_id);
        $this->assertSame([], (new InventoryAudit)->issues());
    }

    public function test_changing_other_fields_does_not_create_entries(): void
    {
        $product = $this->product(10);

        $product->update(['name' => 'Renamed', 'price_cents' => 1234]);

        $this->assertSame([['initial', 10, 10]], $this->ledger($product));
    }

    public function test_history_survives_deleting_the_product(): void
    {
        $product = $this->product(10, ['name' => 'Doomed gadget']);
        $this->inventory()->adjust($product, -1, StockMovement::SALE);

        $product->delete();

        $kept = StockMovement::where('product_name', 'Doomed gadget')->get();
        $this->assertCount(2, $kept);
        $this->assertNull($kept->first()->product_id);
        $this->assertSame([], (new InventoryAudit)->issues());
    }

    // ---- Admin product form ----

    public function test_editing_stock_in_the_product_form_records_who_what_and_why(): void
    {
        $admin = $this->admin();
        $product = $this->product(10);

        $this->actingAs($admin)->put("/admin/products/{$product->id}", [
            'name' => $product->name, 'price_cents' => 1000, 'stock' => 4, 'is_active' => true,
            'stock_note' => 'Damaged in storage',
        ])->assertSessionHasNoErrors();

        $this->assertSame(4, $product->fresh()->stock);
        $movement = StockMovement::latest('id')->first();
        $this->assertSame(-6, $movement->quantity_change);
        $this->assertSame('Damaged in storage', $movement->note);
        $this->assertSame($admin->id, $movement->user_id);
        $this->assertSame([], (new InventoryAudit)->issues());
    }

    public function test_saving_the_form_without_changing_stock_logs_nothing(): void
    {
        $product = $this->product(10);

        $this->actingAs($this->admin())->put("/admin/products/{$product->id}", [
            'name' => 'New name', 'price_cents' => 2000, 'stock' => 10, 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame([['initial', 10, 10]], $this->ledger($product));
        $this->assertSame('New name', $product->fresh()->name);
    }

    public function test_a_stock_note_that_is_too_long_is_rejected(): void
    {
        $product = $this->product(10);

        $this->actingAs($this->admin())->put("/admin/products/{$product->id}", [
            'name' => $product->name, 'price_cents' => 1000, 'stock' => 5, 'is_active' => true,
            'stock_note' => str_repeat('x', 256),
        ])->assertSessionHasErrors('stock_note');

        $this->assertSame(10, $product->fresh()->stock);
    }

    // ---- Integrity check ----

    public function test_a_consistent_catalogue_has_no_issues(): void
    {
        $product = $this->product(10);
        $this->inventory()->adjust($product, -2, StockMovement::SALE);
        $this->product(0);

        $this->assertSame([], (new InventoryAudit)->issues());
        $this->artisan('inventory:verify')->expectsOutputToContain('consistent')->assertSuccessful();
    }

    public function test_stock_changed_behind_the_apps_back_is_reported_as_drift(): void
    {
        $product = $this->product(10);

        DB::table('products')->where('id', $product->id)->update(['stock' => 99]);

        $issues = (new InventoryAudit)->issues();
        $this->assertCount(1, $issues);
        $this->assertSame('drift', $issues[0]['type']);
        $this->assertSame($product->id, $issues[0]['product_id']);
        $this->assertStringContainsString('Stock is 99 but the history adds up to 10', $issues[0]['detail']);
        $this->artisan('inventory:verify')->assertFailed();
    }

    public function test_stock_with_no_history_is_reported_as_untracked(): void
    {
        $product = $this->product(10);
        StockMovement::where('product_id', $product->id)->delete();

        $this->assertSame(['untracked'], array_column((new InventoryAudit)->issues(), 'type'));
    }

    public function test_an_edited_ledger_entry_breaks_the_chain(): void
    {
        $product = $this->product(10);
        $this->inventory()->adjust($product, -2, StockMovement::SALE);
        $this->inventory()->adjust($product, -3, StockMovement::SALE);

        // Someone rewrites the middle entry's balance. (The changes still add up, so only the chain check sees it.)
        StockMovement::where('product_id', $product->id)->where('reason', 'sale')->orderBy('id')->first()->forceFill(['stock_after' => 9])->save();

        $issues = (new InventoryAudit)->issues();
        $this->assertSame(['broken chain'], array_column($issues, 'type'));
        $this->assertStringContainsString('shows a balance of 9', $issues[0]['detail']);
    }

    public function test_a_removed_entry_is_caught_as_drift(): void
    {
        $product = $this->product(10);
        $this->inventory()->adjust($product, -2, StockMovement::SALE);
        StockMovement::where('reason', 'sale')->delete();

        $this->assertSame(['drift'], array_column((new InventoryAudit)->issues(), 'type'));
    }

    // ---- Admin pages ----

    private function inventoryPage(array $query = [], ?User $as = null): array
    {
        return $this->actingAs($as ?? $this->admin())
            ->get('/admin/inventory?'.http_build_query($query))
            ->assertOk()
            ->viewData('page')['props'];
    }

    public function test_only_admins_can_see_the_inventory_log(): void
    {
        $this->get('/admin/inventory')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/inventory')->assertForbidden();
    }

    public function test_the_log_lists_newest_first_with_who_and_why(): void
    {
        $admin = $this->admin();
        $product = $this->product(10);
        $this->inventory()->setStock($product, 8, $admin, 'Recount');

        $props = $this->inventoryPage();
        $first = $props['movements']['data'][0];

        $this->assertSame('Manual adjustment', $first['reason_label']);
        $this->assertSame(-2, $first['quantity_change']);
        $this->assertSame(8, $first['stock_after']);
        $this->assertSame($admin->name, $first['user']['name']);
        $this->assertSame('Recount', $first['note']);
        $this->assertTrue($props['integrity']['ok']);
    }

    public function test_the_log_can_be_filtered_by_reason_product_and_name(): void
    {
        $widget = $this->product(10, ['name' => 'Blue Widget']);
        $gadget = $this->product(10, ['name' => 'Red Gadget']);
        $this->inventory()->adjust($widget, -1, StockMovement::SALE);

        $count = fn (array $q) => count($this->inventoryPage($q)['movements']['data']);

        $this->assertSame(3, $count([]));
        $this->assertSame(1, $count(['reason' => 'sale']));
        $this->assertSame(2, $count(['product' => $widget->id]));
        $this->assertSame(1, $count(['q' => 'gadget']));
        $this->assertSame(3, $count(['reason' => 'not-a-reason']));   // an unknown filter is ignored
        $this->assertSame($widget->id, $this->inventoryPage(['product' => $widget->id])['product']['id']);
        $this->assertSame($gadget->name, $this->inventoryPage(['q' => 'gadget'])['movements']['data'][0]['product_name']);
    }

    public function test_the_log_paginates(): void
    {
        $product = $this->product(100);
        foreach (range(1, 30) as $i) {
            $this->inventory()->adjust($product, -1, StockMovement::SALE);
        }

        $props = $this->inventoryPage();

        $this->assertCount(25, $props['movements']['data']);
        $this->assertSame(31, $props['movements']['total']);
    }

    public function test_the_page_warns_when_stock_and_history_disagree(): void
    {
        $product = $this->product(10);
        DB::table('products')->where('id', $product->id)->update(['stock' => 3]);

        $integrity = $this->inventoryPage()['integrity'];

        $this->assertFalse($integrity['ok']);
        $this->assertSame(1, $integrity['count']);
        $this->assertSame('drift', $integrity['issues'][0]['type']);
    }

    public function test_the_product_page_shows_its_recent_history(): void
    {
        $admin = $this->admin();
        $product = $this->product(10);
        foreach (range(1, 9) as $i) {
            $this->inventory()->adjust($product, -1, StockMovement::SALE);
        }

        $movements = $this->actingAs($admin)->get("/admin/products/{$product->id}/edit")
            ->assertOk()->viewData('page')['props']['movements'];

        $this->assertCount(8, $movements);
        $this->assertSame(1, $movements[0]['stock_after']);   // newest first
    }
}
