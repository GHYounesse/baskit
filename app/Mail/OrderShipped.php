<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShipped extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order->loadMissing('items');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Order #{$this->order->id} is on its way");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.shipped',
            with: ['order' => $this->order],
        );
    }
}
