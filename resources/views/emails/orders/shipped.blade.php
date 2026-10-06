@component('mail::message')
# Your order is on its way

Hi {{ $order->user->name }},

Order **#{{ $order->id }}** has shipped.

@if ($order->tracking_carrier || $order->tracking_number)
@if ($order->tracking_carrier)
**Carrier:** {{ $order->tracking_carrier }}<br>
@endif
@if ($order->tracking_number)
**Tracking number:** {{ $order->tracking_number }}
@endif

@endif
@component('mail::table')
| Product | Qty |
| :------ | :-: |
@foreach ($order->items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} |
@endforeach
@endcomponent

@component('mail::button', ['url' => route('orders.show', $order)])
View your order
@endcomponent

Thanks for shopping with us!<br>
{{ config('app.name') }}
@endcomponent
