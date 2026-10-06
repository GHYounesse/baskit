<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            // Empty string (not null) means "this product has no such option",
            // which lets the unique index below also stop duplicate combinations.
            $table->string('size', 40)->default('');
            $table->string('color', 40)->default('');
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'size', 'color']);
        });

        // A cart line is a product, or one variant of it. Removing a variant removes it from carts.
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->cascadeOnDelete();
        });

        // Orders keep what was bought even if the variant is later deleted.
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->nullOnDelete();
            $table->string('variant_label')->nullable()->after('product_name');
            $table->string('sku', 64)->nullable()->after('variant_label');
        });

        // The stock ledger records which variant moved. No foreign key on purpose:
        // the history keeps the id (and the label) after a variant is deleted.
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('product_variant_id')->nullable()->after('product_id')->index();
            $table->string('variant_label')->nullable()->after('product_name');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['product_variant_id', 'variant_label']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn(['variant_label', 'sku']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
        });

        Schema::dropIfExists('product_variants');
    }
};
