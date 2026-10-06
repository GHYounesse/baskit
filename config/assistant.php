<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shopping assistant
    |--------------------------------------------------------------------------
    |
    | The built-in assistant answers from the store's own data. It needs no
    | external service, so it is on unless switched off here.
    |
    */

    'enabled' => env('ASSISTANT_ENABLED', true),

    // Longer messages than this are cut off before they are processed.
    'max_message_length' => 300,

    // Messages a visitor (or signed-in customer) may send per minute.
    'rate_limit_per_minute' => (int) env('ASSISTANT_RATE_LIMIT', 30),

    /*
    |--------------------------------------------------------------------------
    | What the assistant says about how the store works
    |--------------------------------------------------------------------------
    |
    | These describe what the app really does. The assistant repeats them
    | word for word and never invents anything beyond them, so change them
    | here if the store's rules change.
    |
    */

    'policies' => [
        'cancellation' => 'You can cancel an order yourself from its page until it ships.',
        'refund_timing' => 'The full amount is refunded to your original payment method, usually within 5 to 10 business days.',
        'after_shipping' => "Once an order has shipped it can't be cancelled online. If you need a refund after that, reply to your order confirmation email and mention the order number, and the store can arrange it.",
        'payment' => 'You pay by card at checkout. Payments are processed by Stripe, so the store never sees or stores your card number.',
        'coupons' => "Discount codes are entered in your cart. A code can have a minimum order, an expiry date and a limit on how many times it can be used, and each customer can use a given code once. I can't look up or list codes, but if you have one, try it in your cart and it will tell you straight away if it applies.",
        'shipping' => "Shipping options and delivery times aren't listed in the store yet. Once your order ships you'll get an email with the carrier and tracking number, and the same details appear on your order page.",
        'human' => "There's no live chat. For anything I can't help with, reply to your order confirmation email and the store team will get back to you.",
        'wishlist' => 'Tap the heart on any product to save it to your wishlist, and find everything you saved on the Wishlist page. You need to be signed in to keep a wishlist.',
        'reviews' => 'You can review a product once you have bought it: open the product page, scroll to Customer reviews and choose a star rating. Each customer can leave one review per product, and you can edit or delete yours later.',
        'account' => 'Create an account with Sign up. Anything you add to your cart as a guest is merged into your account cart when you log in.',
    ],

];
