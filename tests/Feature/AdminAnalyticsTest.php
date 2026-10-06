<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday, so weekday and date maths are easy to reason about.
        Carbon::setTestNow('2026-10-07 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /**
     * An order with one line item, placed `$daysAgo` days ago.
     */
    private function order(Product $product, int $quantity = 1, string $status = 'paid', int $daysAgo = 0, ?int $total = null): Order
    {
        $when = now()->subDays($daysAgo);

        // forceFill: created_at is not mass-assignable.
        $order = (new Order)->forceFill([
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'total_cents' => $total ?? $product->price_cents * $quantity,
            'created_at' => $when,
            'updated_at' => $when,
        ]);
        $order->save();
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price_cents' => $product->price_cents,
            'quantity' => $quantity,
        ]);

        return $order;
    }

    private function report(array $query = []): array
    {
        return $this->actingAs($this->admin())
            ->get('/admin/analytics?'.http_build_query($query))
            ->assertOk()
            ->viewData('page')['props'];
    }

    public function test_only_admins_can_see_analytics(): void
    {
        $this->get('/admin/analytics')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/analytics')->assertForbidden();
    }

    public function test_an_empty_store_reports_zeros_without_errors(): void
    {
        $props = $this->report();

        $this->assertSame(0, $props['kpis']['current']['revenue_cents']);
        $this->assertSame(0, $props['kpis']['current']['average_order_cents']);
        $this->assertCount(30, $props['revenueSeries']);
        $this->assertSame([], $props['topProducts']);
        $this->assertSame([], $props['categories']);
        $this->assertCount(7, $props['weekdays']);
    }

    public function test_only_the_known_ranges_are_accepted(): void
    {
        $this->assertSame(30, $this->report()['range']);
        $this->assertSame(7, $this->report(['range' => 7])['range']);
        $this->assertSame(90, $this->report(['range' => 90])['range']);
        $this->assertSame(30, $this->report(['range' => 5])['range']);
        $this->assertSame(30, $this->report(['range' => 'abc'])['range']);
        $this->assertCount(90, $this->report(['range' => 90])['revenueSeries']);
    }

    public function test_revenue_counts_sales_but_not_cancelled_or_refunded_orders(): void
    {
        $product = Product::factory()->create(['price_cents' => 1000]);
        $this->order($product, 2);                       // 2000
        $this->order($product, 1, 'delivered');          // 1000
        $this->order($product, 5, 'cancelled');          // returned
        $this->order($product, 1, 'refunded');           // returned

        $kpis = $this->report()['kpis']['current'];

        $this->assertSame(3000, $kpis['revenue_cents']);
        $this->assertSame(4, $kpis['orders']);
        $this->assertSame(2, $kpis['sales_orders']);
        $this->assertSame(1500, $kpis['average_order_cents']);
        $this->assertSame(6000, $kpis['refunded_cents']);
        $this->assertSame(2, $kpis['refunded_orders']);
    }

    public function test_the_previous_period_is_the_same_number_of_days_just_before(): void
    {
        $product = Product::factory()->create(['price_cents' => 1000]);
        $this->order($product, 1, 'paid', daysAgo: 0);    // this period (7d: today)
        $this->order($product, 1, 'paid', daysAgo: 6);    // this period (oldest day)
        $this->order($product, 2, 'paid', daysAgo: 7);    // previous period (newest day)
        $this->order($product, 3, 'paid', daysAgo: 13);   // previous period (oldest day)
        $this->order($product, 4, 'paid', daysAgo: 14);   // before both: ignored

        $kpis = $this->report(['range' => 7])['kpis'];

        $this->assertSame(2000, $kpis['current']['revenue_cents']);
        $this->assertSame(5000, $kpis['previous']['revenue_cents']);
    }

    public function test_the_daily_series_is_zero_filled_and_lines_up_with_the_previous_period(): void
    {
        $product = Product::factory()->create(['price_cents' => 1000]);
        $this->order($product, 2, 'paid', daysAgo: 0);
        $this->order($product, 1, 'paid', daysAgo: 3);
        $this->order($product, 4, 'paid', daysAgo: 8);   // previous period, day index 5 of 7

        $series = $this->report(['range' => 7])['revenueSeries'];

        $this->assertCount(7, $series);
        $this->assertSame('2026-10-01', $series[0]['date']);
        $this->assertSame('2026-10-07', $series[6]['date']);
        $this->assertSame(2000, $series[6]['revenue_cents']);
        $this->assertSame(1, $series[6]['orders']);
        $this->assertSame(1000, $series[3]['revenue_cents']);
        $this->assertSame(0, $series[1]['revenue_cents']);
        $this->assertSame(0, $series[1]['orders']);
        $this->assertSame('2026-09-24', $series[0]['previous_date']);
        $this->assertSame(4000, $series[5]['previous_revenue_cents']);
    }

    public function test_top_products_rank_by_item_sales_and_skip_returned_orders(): void
    {
        $cheap = Product::factory()->create(['name' => 'Cheap thing', 'price_cents' => 500]);
        $pricey = Product::factory()->create(['name' => 'Pricey thing', 'price_cents' => 5000]);
        $this->order($cheap, 4);                 // 2000, 4 units
        $this->order($pricey, 1);                // 5000
        $this->order($pricey, 3, 'refunded');    // ignored

        $top = $this->report()['topProducts'];

        $this->assertSame(['Pricey thing', 'Cheap thing'], array_column($top, 'name'));
        $this->assertSame([5000, 2000], array_column($top, 'revenue_cents'));
        $this->assertSame([1, 4], array_column($top, 'units'));
    }

    public function test_only_the_top_eight_products_are_listed(): void
    {
        foreach (range(1, 10) as $i) {
            $this->order(Product::factory()->create(['price_cents' => 100 * $i]));
        }

        $this->assertCount(8, $this->report()['topProducts']);
    }

    public function test_categories_show_their_share_and_group_unknown_ones(): void
    {
        $books = Category::create(['name' => 'Books', 'slug' => 'books']);
        $toys = Category::create(['name' => 'Toys', 'slug' => 'toys']);
        $this->order(Product::factory()->create(['category_id' => $books->id, 'price_cents' => 3000]));   // 3000
        $this->order(Product::factory()->create(['category_id' => $toys->id, 'price_cents' => 1000]));    // 1000
        $orphan = $this->order(Product::factory()->create(['category_id' => $toys->id, 'price_cents' => 1500]));
        $orphan->items()->update(['product_id' => null]);                                                    // deleted product

        $categories = $this->report()['categories'];

        $this->assertSame(['Books', 'Uncategorized', 'Toys'], array_column($categories, 'name'));
        $this->assertSame([54.5, 27.3, 18.2], array_column($categories, 'share'));
        $this->assertEqualsWithDelta(100.0, array_sum(array_column($categories, 'share')), 0.2);
    }

    public function test_weekdays_count_sales_orders_monday_first(): void
    {
        $product = Product::factory()->create();
        $this->order($product, 1, 'paid', daysAgo: 0);   // Wed Oct 7
        $this->order($product, 1, 'paid', daysAgo: 0);
        $this->order($product, 1, 'paid', daysAgo: 7);   // Wed Sep 30
        $this->order($product, 1, 'paid', daysAgo: 3);   // Sun Oct 4
        $this->order($product, 1, 'cancelled', daysAgo: 1); // returned: not counted

        $weekdays = $this->report(['range' => 30])['weekdays'];

        $this->assertSame(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], array_column($weekdays, 'day'));
        $this->assertSame([0, 0, 3, 0, 0, 0, 1], array_column($weekdays, 'orders'));
    }
}
