<?php

namespace App\Services\Assistant;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Answers about a customer's own orders: where one is, and whether it can
 * still be cancelled. It only ever looks at the signed-in customer's orders,
 * and it never changes anything: cancelling is done on the order page.
 */
class OrderReplies
{
    public function __construct(private AssistantContext $context) {}

    public function status(Understanding $u, ?User $user): Reply
    {
        if (! $user) {
            return $this->loginNeeded('To look up an order I need you to be signed in, so I only ever show you your own orders.');
        }

        $order = $this->orderFor($u, $user);

        if ($u->entity('order_id') && ! $order) {
            return $this->notFound($u->entity('order_id'), $user);
        }

        if ($order) {
            return $this->single($order);
        }

        return $this->recent($user, 'Which order would you like to know about? Say its number, for example “order #12”.');
    }

    public function cancel(Understanding $u, ?User $user): Reply
    {
        $policy = config('assistant.policies');

        if (! $user) {
            return $this->loginNeeded("{$policy['cancellation']} {$policy['refund_timing']} {$policy['after_shipping']}\n\nSign in and I can check a specific order for you.");
        }

        $order = $this->orderFor($u, $user);

        if ($u->entity('order_id') && ! $order) {
            return $this->notFound($u->entity('order_id'), $user);
        }

        if (! $order) {
            return $this->recent($user, "{$policy['cancellation']} {$policy['refund_timing']}\n\nWhich order do you mean? Say its number, for example “cancel order #12”.");
        }

        $this->context->rememberOrder($order->id);

        $text = match (true) {
            $order->canBeCancelledByCustomer() => "Order #{$order->id} hasn't shipped yet, so you can still cancel it yourself from its page. {$policy['refund_timing']}",
            in_array($order->status, ['shipped', 'delivered'], true) => "Order #{$order->id} is already {$this->words($order)}. {$policy['after_shipping']}",
            in_array($order->status, Order::RETURNED_STATUSES, true) => "Order #{$order->id} is already {$this->words($order)}. {$policy['refund_timing']}",
            default => "Order #{$order->id} is still waiting for payment, so nothing has been charged yet.",
        };

        return Reply::say($text)->orders([$this->card($order)]);
    }

    private function single(Order $order): Reply
    {
        $this->context->rememberOrder($order->id);

        $text = "Order #{$order->id} is {$this->words($order)}.";

        if ($order->tracking_number || $order->tracking_carrier) {
            $text .= ' It shipped'.($order->tracking_carrier ? " with {$order->tracking_carrier}" : '')
                .($order->tracking_number ? ", tracking number {$order->tracking_number}" : '').'.';
        }

        $text .= match ($order->status) {
            'paid' => " It hasn't shipped yet. You'll get an email with the tracking number as soon as it does.",
            'shipped' => ' It is on its way.',
            'delivered' => $order->delivered_at ? ' It was delivered on '.$order->delivered_at->format('M j, Y').'.' : ' It has been delivered.',
            default => '',
        };

        $reply = Reply::say($text)->orders([$this->card($order)]);

        return $order->canBeCancelledByCustomer() ? $reply->suggest('Cancel this order') : $reply;
    }

    private function recent(User $user, string $prompt): Reply
    {
        /** @var Collection<int, Order> $orders */
        $orders = $user->orders()->with('items', 'events')->latest()->limit(3)->get();

        if ($orders->isEmpty()) {
            return Reply::say("You don't have any orders yet. When you place one, I can track it for you here.")
                ->links([['label' => 'Browse the shop', 'href' => '/']]);
        }

        if ($orders->count() === 1) {
            return $this->single($orders->first());
        }

        return Reply::say("Here are your most recent orders. {$prompt}")
            ->orders($orders->map(fn (Order $o) => $this->card($o))->all());
    }

    private function orderFor(Understanding $u, User $user): ?Order
    {
        $id = $u->entity('order_id');

        // "it" / "this order" mean the one we were just talking about.
        if (! $id && $u->entity('pronoun')) {
            $id = $this->context->order();
        }

        return $id ? $user->orders()->with('items', 'events')->find($id) : null;
    }

    private function notFound(int $id, User $user): Reply
    {
        // The same answer whether the order doesn't exist or belongs to someone else.
        $reply = Reply::say("I couldn't find order #{$id} on your account.");

        $orders = $user->orders()->with('items', 'events')->latest()->limit(3)->get();

        return $orders->isEmpty()
            ? $reply
            : $reply->text('Here are your most recent orders:')->orders($orders->map(fn (Order $o) => $this->card($o))->all());
    }

    private function loginNeeded(string $text): Reply
    {
        return Reply::say($text)->links([['label' => 'Log in', 'href' => '/login'], ['label' => 'Sign up', 'href' => '/register']]);
    }

    private function words(Order $order): string
    {
        return match ($order->status) {
            'paid' => 'paid and being prepared',
            'shipped' => 'on its way',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled and refunded',
            'refunded' => 'refunded',
            default => 'waiting for payment',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Order $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'status_label' => ucfirst($this->words($order)),
            'placed' => $order->created_at->format('M j, Y'),
            'total_cents' => $order->total_cents,
            'items' => $order->items->map(fn ($item) => ['name' => $item->display_name, 'quantity' => $item->quantity])->all(),
            'tracking' => $order->tracking_number || $order->tracking_carrier
                ? ['carrier' => $order->tracking_carrier, 'number' => $order->tracking_number]
                : null,
            'timeline' => $order->events->map(fn ($event) => ['title' => $event->title, 'at' => $event->created_at->format('M j, g:i A')])->all(),
            'url' => "/orders/{$order->id}",
            'can_cancel' => $order->canBeCancelledByCustomer(),
        ];
    }
}
