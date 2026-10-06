<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('carts:prune-guests')->daily();

// A daily check that every product's stock still matches its history. A mismatch
// means stock was changed outside the app, so it is logged where someone will see it.
Schedule::command('inventory:verify')
    ->daily()
    ->onFailure(fn () => Log::error('Inventory check failed: stock does not match the stock history. Run `php artisan inventory:verify` for details.'));
