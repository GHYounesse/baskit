<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An append-only ledger of every change to a product's stock.
 *
 * Each product's ledger starts from zero, so the changes always add up to
 * the stock on hand and `stock_after` is the running total. Rows are never
 * edited; a correction is a new row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            // Kept when a product is deleted (set null), with its name copied
            // below, so the history never disappears with the product.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            // sale, return, adjustment, initial, baseline
            $table->string('reason', 24);
            $table->integer('quantity_change');
            $table->unsignedInteger('stock_after');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            // Who caused it: the customer who bought, the admin who edited. Null for the system.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'id']);
            $table->index('created_at');
        });

        // Products that already exist get an opening balance for the stock
        // they hold now, so their ledgers add up from here on.
        DB::table('products')->where('stock', '>', 0)->orderBy('id')->each(function ($product) {
            DB::table('stock_movements')->insert([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'reason' => 'baseline',
                'quantity_change' => $product->stock,
                'stock_after' => $product->stock,
                'note' => 'Opening balance when stock tracking started.',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
