@component('mail::message')
# {{ $order->status === 'cancelled' ? 'Your order was cancelled' : 'Your refund is on its way' }}

Hi {{ $order->user->name }},

We've refunded **${{ number_format($order->total_cents / 100, 2) }}** for order **#{{ $order->id }}** to your original payment method. It can take 5 to 10 business days to appear on your statement.

@component('mail::button', ['url' => route('orders.show', $order)])
View your order
@endcomponent

If you have any questions, just reply to this email.<br>
{{ config('app.name') }}
@endcomponent
