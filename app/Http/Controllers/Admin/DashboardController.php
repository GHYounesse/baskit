<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\AnalyticsService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Products at or below this stock level are flagged as low stock.
     */
    private const LOW_STOCK_THRESHOLD = 5;

    /**
     * The window the revenue figures and chart cover.
     */
    private const TREND_DAYS = 30;

    public function index(AnalyticsService $analytics)
    {
        // Cancelled and refunded orders gave the money back, so they are not revenue.
        $completedOrders = Order::whereNotIn('status', Order::RETURNED_STATUSES);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'revenue_cents' => (clone $completedOrders)->sum('total_cents'),
                'order_count' => Order::count(),
                'product_count' => Product::count(),
                'low_stock_count' => Product::where('stock', '<=', self::LOW_STOCK_THRESHOLD)->count(),
            ],
            // What an admin has to act on today.
            'attention' => [
                'to_ship' => Order::where('status', 'paid')->count(),
                'out_of_stock' => Product::where('stock', '<=', 0)->count(),
            ],
            'trend' => [
                'days' => self::TREND_DAYS,
                ...$analytics->trend(self::TREND_DAYS),
            ],
            'lowStockProducts' => Product::where('stock', '<=', self::LOW_STOCK_THRESHOLD)
                ->orderBy('stock')
                ->limit(6)
                ->get(['id', 'name', 'stock', 'image_path']),
            'recentOrders' => Order::with('user:id,name,email')
                ->latest()
                ->limit(5)
                ->get(['id', 'user_id', 'status', 'total_cents', 'created_at']),
        ]);
    }
}
