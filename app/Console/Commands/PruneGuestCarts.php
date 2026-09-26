<?php

namespace App\Console\Commands;

use App\Models\Cart;
use Illuminate\Console\Command;

class PruneGuestCarts extends Command
{
    protected $signature = 'carts:prune-guests {--days=30 : Delete guest carts not updated for this many days}';

    protected $description = 'Delete abandoned guest carts';

    public function handle(): int
    {
        $deleted = Cart::whereNull('user_id')
            ->where('updated_at', '<', now()->subDays((int) $this->option('days')))
            ->delete();

        $this->info("Deleted {$deleted} abandoned guest cart(s).");

        return self::SUCCESS;
    }
}
