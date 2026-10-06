<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creating, changing and removing variants, with the stock bookkeeping done
 * through the inventory ledger so a product's stock always equals the total
 * of its variants.
 */
class VariantService
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * Add a variant. If this is the product's first, whatever stock the product
     * held on its own is cleared (and recorded), because from now on stock is
     * tracked per variant.
     *
     * @param  array{size?: ?string, color?: ?string, sku?: ?string, stock?: int}  $attributes
     */
    public function create(Product $product, array $attributes, ?User $actor = null): ProductVariant
    {
        return DB::transaction(function () use ($product, $attributes, $actor) {
            $locked = Product::lockForUpdate()->findOrFail($product->id);

            if (! $locked->variants()->exists() && $locked->stock > 0) {
                $this->inventory->setStock($locked, 0, $actor, 'Stock moved to variants.');
            }

            $size = trim((string) ($attributes['size'] ?? ''));
            $color = trim((string) ($attributes['color'] ?? ''));
            $sku = trim((string) ($attributes['sku'] ?? '')) ?: $this->generateSku($locked, $size, $color);

            $variant = ProductVariant::create([
                'product_id' => $locked->id,
                'sku' => $sku,
                'size' => $size,
                'color' => $color,
                'stock' => 0,
                'position' => (int) $locked->variants()->max('position') + 1,
            ]);

            $stock = (int) ($attributes['stock'] ?? 0);

            if ($stock > 0) {
                $this->inventory->adjust($locked->id, $stock, StockMovement::INITIAL, null, $actor, null, $variant);
            }

            return $variant->refresh();
        });
    }

    /**
     * Change a variant's options or SKU, and (when given) its stock.
     *
     * @param  array{size?: ?string, color?: ?string, sku?: ?string, stock?: int}  $attributes
     */
    public function update(ProductVariant $variant, array $attributes, ?User $actor = null, ?string $stockNote = null): ProductVariant
    {
        return DB::transaction(function () use ($variant, $attributes, $actor, $stockNote) {
            $variant->update(array_filter([
                'size' => $attributes['size'] ?? null,
                'color' => $attributes['color'] ?? null,
                'sku' => ($attributes['sku'] ?? '') !== '' ? $attributes['sku'] : null,
            ], fn ($value) => $value !== null));

            if (array_key_exists('stock', $attributes)) {
                $this->inventory->setVariantStock($variant, (int) $attributes['stock'], $actor, $stockNote);
            }

            return $variant->refresh();
        });
    }

    /**
     * Remove a variant. Its remaining stock is taken out through the ledger
     * first, so the product's total stays right. Orders already placed keep
     * their label and SKU.
     */
    public function delete(ProductVariant $variant, ?User $actor = null): void
    {
        DB::transaction(function () use ($variant, $actor) {
            $fresh = ProductVariant::lockForUpdate()->findOrFail($variant->id);

            if ($fresh->stock > 0) {
                $this->inventory->adjust($fresh->product_id, -$fresh->stock, StockMovement::ADJUSTMENT, null, $actor, 'Variant removed.', $fresh);
            }

            $fresh->delete();
        });
    }

    /**
     * A readable, unique SKU when the admin leaves it blank: "WIRE-12-M-BLUE".
     */
    public function generateSku(Product $product, string $size, string $color): string
    {
        $base = implode('-', array_filter([
            strtoupper(Str::substr(Str::slug($product->name, ''), 0, 4)) ?: 'ITEM',
            $product->id,
            Str::upper(Str::slug($size)),
            Str::upper(Str::slug($color)),
        ]));

        $sku = $base;
        $suffix = 2;

        while (ProductVariant::where('sku', $sku)->exists()) {
            $sku = "{$base}-{$suffix}";
            $suffix++;
        }

        return $sku;
    }
}
