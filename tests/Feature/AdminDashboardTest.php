<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function order(string $status, int $totalCents = 1000): Order
    {
        return Order::create(['user_id' => User::factory()->create()->id, 'status' => $status, 'total_cents' => $totalCents]);
    }

    public function test_it_counts_what_needs_attention(): void
    {
        $this->order('paid');
        $this->order('paid');
        $this->order('shipped');
        Product::factory()->create(['stock' => 0, 'category_id' => null]);
        Product::factory()->create(['stock' => 8, 'category_id' => null]);

        $this->actingAs($this->admin())->get('/admin')->assertInertia(fn ($page) => $page
            ->where('attention.to_ship', 2)
            ->where('attention.out_of_stock', 1));
    }

    public function test_it_sends_the_thirty_day_revenue_trend(): void
    {
        $this->order('delivered', 2500);
        $this->order('refunded', 9900);

        $this->actingAs($this->admin())->get('/admin')->assertInertia(fn ($page) => $page
            ->where('trend.days', 30)
            ->has('trend.revenueSeries', 30)
            // The refunded order is not revenue.
            ->where('trend.kpis.current.revenue_cents', 2500)
            ->where('trend.kpis.current.orders', 2));
    }

    public function test_low_stock_products_come_with_a_photo_address(): void
    {
        Product::factory()->create(['stock' => 2, 'category_id' => null, 'image_path' => 'https://example.test/p.jpg']);

        $this->actingAs($this->admin())->get('/admin')->assertInertia(fn ($page) => $page
            ->where('lowStockProducts.0.image_url', 'https://example.test/p.jpg'));
    }

    public function test_customers_cannot_open_it(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }
}
