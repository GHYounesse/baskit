<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ResetDemo extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Wipe the database and reseed the demo data (only when DEMO_MODE is on)';

    public function handle(): int
    {
        if (! config('demo.enabled')) {
            $this->error('Demo mode is off. Set DEMO_MODE=true to allow wiping the database.');

            return self::FAILURE;
        }

        // Uploads made by visitors in the admin go too; the seeder copies the demo images back.
        Storage::disk('public')->deleteDirectory('products');

        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        $this->call('search:reindex');

        return self::SUCCESS;
    }
}
