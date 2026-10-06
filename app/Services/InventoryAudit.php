<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;

/**
 * Checks that the stock on hand and the stock ledger agree.
 *
 * Every product's ledger starts from zero, so two things must hold:
 *  - the changes add up to the stock on hand (otherwise stock was changed
 *    without being recorded, or an entry was lost), and
 *  - each entry's running balance is the previous balance plus its change
 *    (otherwise an entry was edited or removed).
 */
class InventoryAudit
{
    public const DRIFT = 'drift';

    public const UNTRACKED = 'untracked';

    public const BROKEN_CHAIN = 'broken chain';

    /**
     * @return list<array{product_id: int, product_name: string, type: string, detail: string}>
     */
    public function issues(): array
    {
        return [...$this->balanceIssues(), ...$this->chainIssues()];
    }

    /**
     * Stock on hand against what the ledger adds up to.
     *
     * @return list<array{product_id: int, product_name: string, type: string, detail: string}>
     */
    private function balanceIssues(): array
    {
        $ledger = StockMovement::query()
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity_change) as total')
            ->pluck('total', 'product_id');

        $issues = [];

        foreach (Product::query()->orderBy('id')->cursor() as $product) {
            $recorded = (int) ($ledger[$product->id] ?? 0);

            if ($recorded === $product->stock) {
                continue;
            }

            $issues[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'type' => $ledger->has($product->id) ? self::DRIFT : self::UNTRACKED,
                'detail' => $ledger->has($product->id)
                    ? "Stock is {$product->stock} but the history adds up to {$recorded}."
                    : "Stock is {$product->stock} but nothing has been recorded.",
            ];
        }

        return $issues;
    }

    /**
     * Each entry's balance against the entries before it. Reports a product once.
     *
     * @return list<array{product_id: int, product_name: string, type: string, detail: string}>
     */
    private function chainIssues(): array
    {
        $issues = [];
        $current = null;
        $running = 0;
        $reported = false;

        $entries = StockMovement::query()
            ->whereNotNull('product_id')
            ->orderBy('product_id')
            ->orderBy('id')
            ->cursor();

        foreach ($entries as $entry) {
            if ($entry->product_id !== $current) {
                $current = $entry->product_id;
                $running = 0;
                $reported = false;
            }

            $running += $entry->quantity_change;

            if (! $reported && $entry->stock_after !== $running) {
                $issues[] = [
                    'product_id' => $entry->product_id,
                    'product_name' => $entry->product_name,
                    'type' => self::BROKEN_CHAIN,
                    'detail' => "Entry #{$entry->id} shows a balance of {$entry->stock_after}, but the entries before it add up to {$running}.",
                ];
                $reported = true;
            }
        }

        return $issues;
    }
}
