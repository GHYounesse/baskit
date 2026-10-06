<?php

namespace App\Console\Commands;

use App\Services\Search\ProductSearch;
use App\Services\Search\StoreSearch;
use Illuminate\Console\Command;

class ReindexProductSearch extends Command
{
    protected $signature = 'search:reindex';

    protected $description = 'Rebuild the product search index from the database';

    public function handle(ProductSearch $search): int
    {
        $count = $search->reindex();
        StoreSearch::forgetVocabulary();

        $this->info($count > 0
            ? "Indexed {$count} products."
            : 'Nothing to index (this database searches live and needs no index).');

        return self::SUCCESS;
    }
}
