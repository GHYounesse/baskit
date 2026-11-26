<?php

return [

    /*
    | Demo mode lets `php artisan demo:reset` wipe the database and reseed it,
    | and schedules that nightly. Leave it off for any site with real data.
    */
    'enabled' => (bool) env('DEMO_MODE', false),

    // The time (24-hour, server time) of the nightly reset.
    'reset_at' => env('DEMO_RESET_AT', '04:00'),
];
