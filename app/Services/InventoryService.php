<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one place stock is changed on purpose.
 *
 * Every change locks the product row, refuses to go below zero, and writes
 * its ledger entry in the same transaction, so the stock on hand and the
 * history can't disagree. Stock is written with a plain query (no model
 * events), because the entry is written here; a stock change made some other
 * way is caught by the Product model's safety net instead.
 */
class InventoryService
{
    /**
     * Add (positive) or remove (negative) units.
     *
     * @throws InsufficientStockException when it would take stock below zero
     */
    public function adjust(
        Product|int $product,
        int $change,
        string $reason,
        ?Order $order = null,
        User|int|null $actor = null,
        ?string $note = null,
    ): StockMovement {
        $productId = $product instanceof Product ? $product->id : $product;

        return DB::transaction(function () use ($product, $productId, $change, $reason, $order, $actor, $note) {
            $locked = Product::lockForUpdate()->findOrFail($productId);
            $new = $locked->stock + $change;

            if ($new < 0) {
                throw new InsufficientStockException($locked->name);
            }

            Product::whereKey($productId)->update(['stock' => $new]);

            // Keep an instance the caller is holding up to date.
            if ($product instanceof Product) {
                $product->forceFill(['stock' => $new])->syncOriginalAttribute('stock');
            }

            return $this->record($locked, $change, $new, $reason, $order, $actor, $note);
        });
    }

    /**
     * Set stock to an exact number (what an admin does in the product form).
     *
     * The change is worked out from the stock on hand right now, under the
     * lock, not from what the form showed when it was opened, so an order
     * placed in between is never overwritten silently. Returns null when
     * nothing changed.
     */
    public function setStock(Product $product, int $newStock, User|int|null $actor = null, ?string $note = null): ?StockMovement
    {
        return DB::transaction(function () use ($product, $newStock, $actor, $note) {
            $locked = Product::lockForUpdate()->findOrFail($product->id);
            $change = $newStock - $locked->stock;

            if ($change === 0) {
                $product->forceFill(['stock' => $newStock])->syncOriginalAttribute('stock');

                return null;
            }

            Product::whereKey($locked->id)->update(['stock' => $newStock]);
            $product->forceFill(['stock' => $newStock])->syncOriginalAttribute('stock');

            return $this->record($locked, $change, $newStock, StockMovement::ADJUSTMENT, null, $actor, $note);
        });
    }

    /**
     * Opening entry for a product created with stock on hand.
     */
    public function recordInitial(Product $product, User|int|null $actor = null): ?StockMovement
    {
        if ($product->stock <= 0) {
            return null;
        }

        return $this->record($product, $product->stock, $product->stock, StockMovement::INITIAL, null, $actor, null);
    }

    private function record(
        Product $product,
        int $change,
        int $stockAfter,
        string $reason,
        ?Order $order,
        User|int|null $actor,
        ?string $note,
    ): StockMovement {
        $note = trim((string) $note);

        return StockMovement::create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'reason' => $reason,
            'quantity_change' => $change,
            'stock_after' => $stockAfter,
            'order_id' => $order?->id,
            'user_id' => $actor instanceof User ? $actor->id : $actor,
            'note' => $note === '' ? null : $note,
        ]);
    }
}
