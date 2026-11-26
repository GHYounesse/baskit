<?php

return [

    /*
    | Demo mode lets `php artisan demo:reset` wipe the database and reseed it,
    | and schedules that nightly. Leave it off for any site with real data.
    */
    'enabled' => (bool) env('DEMO_MODE', false),

    // What the demo banner shows visitors. These are the public demo accounts from the seeders.
    'banner' => [
        'test_card' => '4242 4242 4242 4242',
        'password' => 'password',
        'accounts' => [
            ['role' => 'Admin', 'email' => 'admin@example.com'],
            ['role' => 'Customer', 'email' => 'customer@example.com'],
        ],
    ],

    // The time (24-hour, server time) of the nightly reset.
    'reset_at' => env('DEMO_RESET_AT', '04:00'),
];
