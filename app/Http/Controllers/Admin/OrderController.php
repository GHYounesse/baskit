<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\PaymentRefundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RefundOrderRequest;
use App\Http\Requests\Admin\ShipOrderRequest;
use App\Models\Order;
use App\Services\OrderLifecycleService;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(private OrderLifecycleService $lifecycle) {}

    public function index()
    {
        $this->authorize('manage', Order::class);

        return Inertia::render('Admin/Orders/Index', [
            'orders' => Order::with('user')->latest()->paginate(20),
        ]);
    }

    public function show(Order $order)
    {
        $this->authorize('manage', $order);

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order->load('user', 'items'),
            'timeline' => $order->timeline(),
            'actions' => [
                'ship' => $order->canBeShipped(),
                'deliver' => $order->canBeDelivered(),
                'refund' => $order->canBeRefunded(),
            ],
        ]);
    }

    public function ship(ShipOrderRequest $request, Order $order)
    {
        $this->authorize('manage', $order);

        return $this->run(
            fn () => $this->lifecycle->ship(
                $order,
                $request->validated('tracking_carrier'),
                $request->validated('tracking_number'),
                $request->user(),
            ),
            'Order marked as shipped.',
        );
    }

    public function deliver(Order $order)
    {
        $this->authorize('manage', $order);

        return $this->run(
            fn () => $this->lifecycle->deliver($order, request()->user()),
            'Order marked as delivered.',
        );
    }

    public function refund(RefundOrderRequest $request, Order $order)
    {
        $this->authorize('manage', $order);

        return $this->run(
            fn () => $this->lifecycle->refund(
                $order,
                $request->user(),
                $request->validated('reason'),
                $request->boolean('restock', true),
            ),
            'Order refunded.',
        );
    }

    /**
     * Run a lifecycle action, turning the expected failures into a message
     * the admin can read instead of a server error.
     */
    private function run(callable $action, string $success)
    {
        try {
            $action();
        } catch (InvalidOrderTransitionException|PaymentRefundException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return back()->with('success', $success);
    }
}
