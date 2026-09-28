@component('mail::message')
# Thanks for your order!

Hi {{ $order->user->name }},

We've received your order **#{{ $order->id }}** and it's being prepared.

@component('mail::table')
| Product | Qty | Price |
| :------ | :-: | ----: |
@foreach ($order->items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} | ${{ number_format($item->unit_price_cents * $item->quantity / 100, 2) }} |
@endforeach
@endcomponent

**Total: ${{ number_format($order->total_cents / 100, 2) }}**

@if ($order->shipping_address)
## Shipping to

{{ $order->shipping_address['line1'] ?? '' }}
@if (!empty($order->shipping_address['line2']))
{{ $order->shipping_address['line2'] }}
@endif

{{ collect([$order->shipping_address['city'] ?? null, $order->shipping_address['state'] ?? null, $order->shipping_address['postal_code'] ?? null])->filter()->implode(', ') }}

{{ $order->shipping_address['country'] ?? '' }}
@endif

@component('mail::button', ['url' => route('orders.show', $order)])
View your order
@endcomponent

Thanks for shopping with us!<br>
{{ config('app.name') }}
@endcomponent
