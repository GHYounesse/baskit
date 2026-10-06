<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The one place stock is changed on purpose.
 *
 * Every change locks the product row (and its variant, when it has one),
 * refuses to go below zero, and writes its ledger entry in the same
 * transaction, so the stock on hand and the history can't disagree. Stock is
 * written with a plain query (no model events), because the entry is written
 * here; a stock change made some other way is caught by the Product model's
 * safety net instead.
 *
 * A product that has variants holds no stock of its own: its `stock` is the
 * total of its variants', kept in step here. Every entry for a variant carries
 * the variant's own running balance.
 */
class InventoryService
{
    /**
     * Add (positive) or remove (negative) units, of a product or of one of its variants.
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
        ProductVariant|int|null $variant = null,
    ): StockMovement {
        $productId = $product instanceof Product ? $product->id : $product;
        $variantId = $variant instanceof ProductVariant ? $variant->id : $variant;

        return DB::transaction(function () use ($product, $variant, $productId, $variantId, $change, $reason, $order, $actor, $note) {
            // Always lock the product first, then the variant, so concurrent
            // changes take their locks in the same order and cannot deadlock.
            $lockedProduct = Product::lockForUpdate()->findOrFail($productId);
            $lockedVariant = $variantId
                ? ProductVariant::lockForUpdate()->where('product_id', $productId)->findOrFail($variantId)
                : null;

            if (! $lockedVariant && $lockedProduct->variants()->exists()) {
                throw new LogicException("{$lockedProduct->name} has variants; its stock can only change through one of them.");
            }

            $current = $lockedVariant ? $lockedVariant->stock : $lockedProduct->stock;
            $new = $current + $change;

            if ($new < 0) {
                throw new InsufficientStockException($lockedVariant ? "{$lockedProduct->name} ({$lockedVariant->label})" : $lockedProduct->name);
            }

            if ($lockedVariant) {
                ProductVariant::whereKey($variantId)->update(['stock' => $new]);
                Product::whereKey($productId)->update(['stock' => $lockedProduct->stock + $change]);
            } else {
                Product::whereKey($productId)->update(['stock' => $new]);
            }

            // Keep any instance the caller is holding up to date.
            if ($product instanceof Product) {
                // (A product's total moves by the same amount whether or not a variant did the changing.)
                $product->forceFill(['stock' => $lockedProduct->stock + $change])->syncOriginalAttribute('stock');
            }

            if ($variant instanceof ProductVariant) {
                $variant->forceFill(['stock' => $new])->syncOriginalAttribute('stock');
            }

            return $this->record($lockedProduct, $change, $new, $reason, $order, $actor, $note, $lockedVariant);
        });
    }

    /**
     * Set a product's stock to an exact number (what an admin does in the product form).
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

            if ($locked->variants()->exists()) {
                throw new LogicException("{$locked->name} has variants; set the stock of a variant instead.");
            }

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
     * Set one variant's stock to an exact number, from the stock on hand now.
     */
    public function setVariantStock(ProductVariant $variant, int $newStock, User|int|null $actor = null, ?string $note = null): ?StockMovement
    {
        return DB::transaction(function () use ($variant, $newStock, $actor, $note) {
            $current = ProductVariant::lockForUpdate()->findOrFail($variant->id)->stock;
            $change = $newStock - $current;

            if ($change === 0) {
                $variant->forceFill(['stock' => $newStock])->syncOriginalAttribute('stock');

                return null;
            }

            return $this->adjust($variant->product_id, $change, StockMovement::ADJUSTMENT, null, $actor, $note, $variant);
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
        ?ProductVariant $variant = null,
    ): StockMovement {
        $note = trim((string) $note);

        return StockMovement::create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_variant_id' => $variant?->id,
            'variant_label' => $variant?->label,
            'reason' => $reason,
            'quantity_change' => $change,
            'stock_after' => $stockAfter,
            'order_id' => $order?->id,
            'user_id' => $actor instanceof User ? $actor->id : $actor,
            'note' => $note === '' ? null : $note,
        ]);
    }
}
