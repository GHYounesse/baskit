<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Services\VariantService;
use Illuminate\Database\Seeder;

class VariantSeeder extends Seeder
{
    private const LETTER_SIZES = ['S', 'M', 'L', 'XL'];

    private const SHOE_SIZES = ['40', '41', '42', '43', '44'];

    /**
     * Which clothing products come in which options. Anything not listed here
     * stays a single, simple product.
     *
     * @var array<string, array{sizes: list<string>, colors: list<string>}>
     */
    private const OPTIONS = [
        'Cotton T-Shirt' => ['sizes' => self::LETTER_SIZES, 'colors' => ['White', 'Black', 'Navy']],
        'Denim Jacket' => ['sizes' => self::LETTER_SIZES, 'colors' => ['Indigo', 'Black']],
        'Running Shoes' => ['sizes' => self::SHOE_SIZES, 'colors' => ['Grey', 'Blue']],
        'Slim Fit Jeans' => ['sizes' => self::LETTER_SIZES, 'colors' => ['Indigo', 'Black']],
        'Hooded Sweatshirt' => ['sizes' => self::LETTER_SIZES, 'colors' => ['Charcoal', 'Olive', 'Navy']],
        'Wool Beanie' => ['sizes' => [], 'colors' => ['Grey', 'Navy', 'Mustard']],
        'Baseball Cap' => ['sizes' => [], 'colors' => ['Black', 'White', 'Red']],
    ];

    /**
     * Gives the demo clothes sizes and colours, each with its own SKU and
     * stock. Done through VariantService so the stock ledger stays
     * consistent. Safe to run again: products that already have variants are
     * left alone.
     */
    public function run(VariantService $variants): void
    {
        foreach (self::OPTIONS as $name => $options) {
            $product = Product::where('name', $name)->first();

            if (! $product || $product->hasVariants()) {
                continue;
            }

            foreach ($options['sizes'] ?: [''] as $size) {
                foreach ($options['colors'] ?: [''] as $color) {
                    $variants->create($product, [
                        'size' => $size,
                        'color' => $color,
                        // Mostly in stock, with the odd sold-out option to show how that looks.
                        'stock' => random_int(0, 5) === 0 ? 0 : random_int(3, 25),
                    ]);
                }
            }
        }
    }
}
