<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;

/**
 * Checks that the stock on hand and the stock ledger agree.
 *
 * Every ledger starts from zero, so these must hold:
 *  - a product's changes (variants included) add up to its stock on hand,
 *  - a variant's changes add up to that variant's stock, and
 *  - each entry's running balance is the previous balance plus its change,
 *    counted separately for the product itself and for each of its variants
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
        return [...$this->productBalanceIssues(), ...$this->variantBalanceIssues(), ...$this->chainIssues()];
    }

    /**
     * Product stock on hand against what its whole ledger adds up to.
     *
     * @return list<array{product_id: int, product_name: string, type: string, detail: string}>
     */
    private function productBalanceIssues(): array
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
     * Each variant's stock against its own ledger.
     *
     * @return list<array{product_id: int, product_name: string, type: string, detail: string}>
     */
    private function variantBalanceIssues(): array
    {
        $ledger = StockMovement::query()
            ->whereNotNull('product_variant_id')
            ->groupBy('product_variant_id')
            ->selectRaw('product_variant_id, SUM(quantity_change) as total')
            ->pluck('total', 'product_variant_id');

        $issues = [];

        foreach (ProductVariant::query()->with('product:id,name')->orderBy('id')->cursor() as $variant) {
            $recorded = (int) ($ledger[$variant->id] ?? 0);

            if ($recorded === $variant->stock) {
                continue;
            }

            $issues[] = [
                'product_id' => $variant->product_id,
                'product_name' => "{$variant->product->name} ({$variant->label})",
                'type' => $ledger->has($variant->id) ? self::DRIFT : self::UNTRACKED,
                'detail' => $ledger->has($variant->id)
                    ? "Stock is {$variant->stock} but the history adds up to {$recorded}."
                    : "Stock is {$variant->stock} but nothing has been recorded.",
            ];
        }

        return $issues;
    }

    /**
     * Each entry's balance against the entries before it, for the product
     * itself and for each variant separately. Reports each ledger once.
     *
     * @return list<array{product_id: int, product_name: string, type: string, detail: string}>
     */
    private function chainIssues(): array
    {
        $issues = [];
        $currentKey = null;
        $running = 0;
        $reported = false;

        $entries = StockMovement::query()
            ->whereNotNull('product_id')
            ->orderBy('product_id')
            ->orderBy('product_variant_id')
            ->orderBy('id')
            ->cursor();

        foreach ($entries as $entry) {
            $key = $entry->product_id.':'.($entry->product_variant_id ?? 0);

            if ($key !== $currentKey) {
                $currentKey = $key;
                $running = 0;
                $reported = false;
            }

            $running += $entry->quantity_change;

            if (! $reported && $entry->stock_after !== $running) {
                $name = $entry->variant_label ? "{$entry->product_name} ({$entry->variant_label})" : $entry->product_name;

                $issues[] = [
                    'product_id' => $entry->product_id,
                    'product_name' => $name,
                    'type' => self::BROKEN_CHAIN,
                    'detail' => "Entry #{$entry->id} shows a balance of {$entry->stock_after}, but the entries before it add up to {$running}.",
                ];
                $reported = true;
            }
        }

        return $issues;
    }
}
