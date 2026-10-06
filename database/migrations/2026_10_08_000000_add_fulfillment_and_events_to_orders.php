<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tracking_carrier', 40)->nullable()->after('shipping_address');
            $table->string('tracking_number', 100)->nullable()->after('tracking_carrier');
            $table->timestamp('shipped_at')->nullable()->after('tracking_number');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->timestamp('refunded_at')->nullable()->after('delivered_at');
            $table->string('stripe_refund_id')->nullable()->after('refunded_at');
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // placed, shipped, delivered, cancelled, refunded
            $table->string('type', 32);
            $table->text('message')->nullable();
            // Who did it: the customer or an admin. Null for the system.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        $this->backfillExistingOrders();
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'tracking_carrier', 'tracking_number', 'shipped_at',
                'delivered_at', 'refunded_at', 'stripe_refund_id',
            ]);
        });
    }

    /**
     * Give orders that predate the timeline a history that matches their
     * status: they were placed, and if shipped or cancelled, that happened
     * at their last update (the best date we have).
     */
    private function backfillExistingOrders(): void
    {
        DB::table('orders')->orderBy('id')->each(function ($order) {
            $events = [['type' => 'placed', 'message' => null, 'created_at' => $order->created_at]];

            if ($order->status === 'shipped') {
                $events[] = ['type' => 'shipped', 'message' => 'Order shipped.', 'created_at' => $order->updated_at];
                DB::table('orders')->where('id', $order->id)->update(['shipped_at' => $order->updated_at]);
            }

            if ($order->status === 'cancelled') {
                $events[] = ['type' => 'cancelled', 'message' => 'Order cancelled.', 'created_at' => $order->updated_at];
            }

            foreach ($events as $event) {
                DB::table('order_events')->insert($event + ['order_id' => $order->id]);
            }
        });
    }
};
