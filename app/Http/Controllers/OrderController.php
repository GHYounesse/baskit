<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Orders/Index', [
            'orders' => $request->user()->orders()->with('items')->latest()->get(),
        ]);
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order); // see Policy note below

        return Inertia::render('Orders/Show', ['order' => $order->load('items')]);
    }
}
