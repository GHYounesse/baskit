<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\PaymentRefundException;
use App\Models\Order;
use App\Services\OrderLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Orders/Index', [
            'orders' => $request->user()->orders()->with('items')->latest()->get(),
        ]);
    }

    public function show(Request $request, Order $order)
    {
        $this->authorize('view', $order); // see Policy note below

        return Inertia::render('Orders/Show', [
            'order' => $order->load('items'),
            'timeline' => $order->timeline(),
            'canCancel' => $order->canBeCancelledByCustomer() && $request->user()->can('cancel', $order),
        ]);
    }

    /**
     * Let a customer cancel their own order until it ships. The refund goes
     * back to their card and the items return to stock.
     */
    public function cancel(Request $request, Order $order, OrderLifecycleService $lifecycle)
    {
        $this->authorize('cancel', $order);

        try {
            $lifecycle->refund($order, $request->user(), restock: true, byCustomer: true);
        } catch (InvalidOrderTransitionException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        } catch (PaymentRefundException $e) {
            // The provider's wording is for admins and logs, not for customers.
            Log::error('Customer cancellation refund failed', ['order' => $order->id, 'error' => $e->getMessage()]);

            return back()->withErrors(['order' => "We couldn't process your refund right now. Nothing has changed, so please try again in a moment or contact us."]);
        }

        return back()->with('success', 'Order cancelled. Your refund is on its way.');
    }
}
