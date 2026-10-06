<?php

namespace App\Console\Commands;

use App\Services\InventoryAudit;
use Illuminate\Console\Command;

class VerifyInventory extends Command
{
    protected $signature = 'inventory:verify';

    protected $description = 'Check that every product\'s stock matches its stock history';

    public function handle(InventoryAudit $audit): int
    {
        $issues = $audit->issues();

        if ($issues === []) {
            $this->info('Inventory is consistent: every product\'s stock matches its history.');

            return self::SUCCESS;
        }

        $this->error(count($issues).' problem'.(count($issues) === 1 ? '' : 's').' found.');
        $this->table(
            ['Product', 'Problem', 'Detail'],
            array_map(fn (array $i) => ["#{$i['product_id']} {$i['product_name']}", $i['type'], $i['detail']], $issues),
        );

        return self::FAILURE;
    }
}
