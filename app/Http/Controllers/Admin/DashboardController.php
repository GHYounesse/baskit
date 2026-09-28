<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Products at or below this stock level are flagged as low stock.
     */
    private const LOW_STOCK_THRESHOLD = 5;

    public function index()
    {
        // Cancelled orders never brought in revenue.
        $completedOrders = Order::where('status', '!=', 'cancelled');

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'revenue_cents' => (clone $completedOrders)->sum('total_cents'),
                'order_count' => Order::count(),
                'product_count' => Product::count(),
                'low_stock_count' => Product::where('stock', '<=', self::LOW_STOCK_THRESHOLD)->count(),
            ],
            'lowStockProducts' => Product::where('stock', '<=', self::LOW_STOCK_THRESHOLD)
                ->orderBy('stock')
                ->limit(10)
                ->get(['id', 'name', 'stock']),
            'recentOrders' => Order::with('user:id,name,email')
                ->latest()
                ->limit(5)
                ->get(['id', 'user_id', 'status', 'total_cents', 'created_at']),
        ]);
    }
}
