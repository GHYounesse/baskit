<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('token', 64)->nullable()->unique()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn('token');
        });
    }
};
