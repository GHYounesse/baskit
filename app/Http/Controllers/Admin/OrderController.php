<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
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

    public function update(UpdateOrderStatusRequest $request, Order $order)
    {
        $order->update($request->validated());

        return back()->with('success', 'Order updated.');
    }
}
