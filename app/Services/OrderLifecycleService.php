<?php

namespace App\Services;

use App\Exceptions\InvalidOrderTransitionException;
use App\Mail\OrderRefunded;
use App\Mail\OrderShipped;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Payments\PaymentRefunder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Every status change an order goes through after it's paid. Each one locks
 * the order, checks it is in a state where the change makes sense, and writes
 * a timeline event, so two admins (or an admin and the customer) acting at the
 * same moment can't push an order into an impossible state.
 */
class OrderLifecycleService
{
    public function __construct(private PaymentRefunder $refunder, private InventoryService $inventory) {}

    public function ship(Order $order, ?string $carrier, ?string $trackingNumber, ?User $actor = null): Order
    {
        $carrier = $this->clean($carrier);
        $trackingNumber = $this->clean($trackingNumber);

        $order = DB::transaction(function () use ($order, $carrier, $trackingNumber, $actor) {
            $order = $this->lock($order);

            if (! $order->canBeShipped()) {
                throw new InvalidOrderTransitionException('Only paid orders that have not shipped can be marked as shipped.');
            }

            $order->update([
                'status' => 'shipped',
                'shipped_at' => now(),
                'tracking_carrier' => $carrier,
                'tracking_number' => $trackingNumber,
            ]);

            $order->events()->create([
                'type' => 'shipped',
                'message' => $this->shippingMessage($carrier, $trackingNumber),
                'user_id' => $actor?->id,
            ]);

            return $order;
        });

        Mail::to($order->user->email)->queue(new OrderShipped($order));

        return $order;
    }

    public function deliver(Order $order, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $actor) {
            $order = $this->lock($order);

            if (! $order->canBeDelivered()) {
                throw new InvalidOrderTransitionException('Only shipped orders can be marked as delivered.');
            }

            $order->update(['status' => 'delivered', 'delivered_at' => now()]);

            $order->events()->create([
                'type' => 'delivered',
                'message' => 'Order delivered.',
                'user_id' => $actor?->id,
            ]);

            return $order;
        });
    }

    /**
     * Refund the whole order through the payment provider.
     *
     * An order that hasn't shipped is "cancelled"; one that already left is
     * "refunded". If the provider call fails nothing is changed. If it
     * succeeds but we crash before saving, retrying is safe: the idempotency
     * key returns the same refund instead of paying out twice.
     */
    public function refund(
        Order $order,
        ?User $actor = null,
        ?string $reason = null,
        bool $restock = true,
        bool $byCustomer = false,
    ): Order {
        $reason = $this->clean($reason);

        $order = DB::transaction(function () use ($order, $actor, $reason, $restock, $byCustomer) {
            $order = $this->lock($order);

            if ($byCustomer && ! $order->canBeCancelledByCustomer()) {
                throw new InvalidOrderTransitionException("This order has already shipped, so it can't be cancelled online.");
            }

            if (! $order->canBeRefunded()) {
                throw new InvalidOrderTransitionException("This order can't be refunded.");
            }

            $refundId = $this->refunder->refund($order->stripe_payment_intent_id, "order-{$order->id}-refund");

            $cancelling = $order->status === 'paid';

            $order->update([
                'status' => $cancelling ? 'cancelled' : 'refunded',
                'refunded_at' => now(),
                'stripe_refund_id' => $refundId,
            ]);

            if ($restock) {
                $this->restock($order, $actor);
            }

            $this->releaseCoupon($order);

            $order->events()->create([
                'type' => $cancelling ? 'cancelled' : 'refunded',
                'message' => $this->refundMessage($order, $byCustomer, $restock, $reason),
                'user_id' => $actor?->id,
            ]);

            return $order;
        });

        Mail::to($order->user->email)->queue(new OrderRefunded($order));

        return $order;
    }

    /**
     * The payment was refunded outside the app (the Stripe dashboard). Bring
     * the order in line without calling Stripe again. Stock is left alone,
     * because we can't know whether the goods came back.
     */
    public function syncRefundFromProvider(Order $order): Order
    {
        $changed = false;

        $order = DB::transaction(function () use ($order, &$changed) {
            $order = $this->lock($order);

            if (in_array($order->status, Order::RETURNED_STATUSES, true)) {
                return $order;
            }

            $cancelling = $order->status === 'paid';

            $order->update([
                'status' => $cancelling ? 'cancelled' : 'refunded',
                'refunded_at' => now(),
            ]);

            $this->releaseCoupon($order);

            $order->events()->create([
                'type' => $cancelling ? 'cancelled' : 'refunded',
                'message' => $this->formatMoney($order->total_cents).' refunded from the Stripe dashboard.',
            ]);

            $changed = true;

            return $order;
        });

        if ($changed) {
            Mail::to($order->user->email)->queue(new OrderRefunded($order));
        }

        return $order;
    }

    private function lock(Order $order): Order
    {
        return Order::with('user')->lockForUpdate()->findOrFail($order->id);
    }

    private function restock(Order $order, ?User $actor): void
    {
        foreach ($order->items()->whereNotNull('product_id')->get() as $item) {
            // A line for a variant that has since been deleted has nowhere to go back to.
            if ($item->variant_label !== null && $item->product_variant_id === null) {
                continue;
            }

            $this->inventory->adjust($item->product_id, $item->quantity, StockMovement::RETURN, $order, $actor, null, $item->product_variant_id);
        }
    }

    /**
     * A refunded order no longer counts as a use of its coupon, so the
     * customer can use the code again and a limited code frees up a slot.
     */
    private function releaseCoupon(Order $order): void
    {
        if ($order->coupon_id) {
            Coupon::whereKey($order->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
        }
    }

    private function shippingMessage(?string $carrier, ?string $trackingNumber): string
    {
        $message = $carrier ? "Shipped with {$carrier}." : 'Order shipped.';

        return $trackingNumber ? "{$message} Tracking number: {$trackingNumber}." : $message;
    }

    private function refundMessage(Order $order, bool $byCustomer, bool $restock, ?string $reason): string
    {
        $parts = [
            ($byCustomer ? 'Cancelled by the customer. ' : '').$this->formatMoney($order->total_cents).' refunded to the original payment method.',
        ];

        if ($restock) {
            $parts[] = 'Items returned to stock.';
        }

        if ($reason) {
            $parts[] = "Reason: {$reason}";
        }

        return implode(' ', $parts);
    }

    private function formatMoney(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
