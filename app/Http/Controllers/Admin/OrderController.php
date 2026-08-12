<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Orders/Index', [
            'orders' => Order::with('user')->latest()->paginate(20),
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $request->validate(['status' => 'required|in:pending,paid,shipped,cancelled']);
        $order->update(['status' => $request->status]);
        return back()->with('success', 'Order updated.');
    }
}
