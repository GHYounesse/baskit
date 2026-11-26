<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('review_summary')->nullable();
            $table->string('review_summary_hash', 32)->nullable();
            $table->timestamp('review_summary_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['review_summary', 'review_summary_hash', 'review_summary_at']);
        });
    }
};
