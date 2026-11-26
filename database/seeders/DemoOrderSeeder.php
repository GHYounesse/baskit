<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

class DemoOrderSeeder extends Seeder
{
    private const ADDRESS = [
        'line1' => '12 Rue des Orangers',
        'line2' => null,
        'city' => 'Casablanca',
        'state' => null,
        'postal_code' => '20000',
        'country' => 'MA',
    ];

    /**
     * Orders for the demo customer in every state a shopper can see: just placed and shipped. They give the order list, the timeline and
     * the status badges something to show. Like the review orders, they do not move stock.
     * Runs once: skipped if the customer already has an order that is not delivered.
     */
    public function run(): void
    {
        $customer = User::where('email', 'customer@example.com')->first();

        if (! $customer || $customer->orders()->where('status', '!=', 'delivered')->exists()) {
            return;
        }

        // Just placed and paid, waiting to ship.
        $this->place($customer, now()->subHours(3), 'paid', ['table-lamp' => 1, 'ceramic-coffee-mug' => 2]);

        // On its way.
        $shipped = $this->place($customer, now()->subDays(3), 'shipped', ['wireless-headphones' => 1, 'wireless-mouse' => 1], [
            'shipped_at' => now()->subDays(2),
            'tracking_carrier' => 'UPS',
            'tracking_number' => '1Z999AA10123456784',
        ]);
        $this->event($shipped, 'shipped', 'Shipped with UPS.', now()->subDays(2));
    }

    /**
     * @param  array<string, int>  $lines  Product slug => quantity.
     * @param  array<string, mixed>  $extra
     */
    private function place(User $customer, CarbonInterface $at, string $status, array $lines, array $extra = []): Order
    {
        $products = Product::whereIn('slug', array_keys($lines))->get()->keyBy('slug');

        $subtotal = 0;
        foreach ($lines as $slug => $quantity) {
            $subtotal += $products[$slug]->price_cents * $quantity;
        }

        $discount = (int) round($subtotal * (($extra['discount_percent'] ?? 0) / 100));
        unset($extra['discount_percent']);

        // forceFill, because created_at is not mass-assignable (see ReviewSeeder).
        $order = (new Order)->forceFill([
            'user_id' => $customer->id,
            'status' => $status,
            'total_cents' => $subtotal - $discount,
            'discount_cents' => $discount,
            'shipping_address' => self::ADDRESS,
            'created_at' => $at,
            'updated_at' => $at,
            ...$extra,
        ]);
        $order->save();

        foreach ($lines as $slug => $quantity) {
            $order->items()->create([
                'product_id' => $products[$slug]->id,
                'product_name' => $products[$slug]->name,
                'unit_price_cents' => $products[$slug]->price_cents,
                'quantity' => $quantity,
            ]);
        }

        return $order;
    }

    private function event(Order $order, string $type, string $message, CarbonInterface $at): void
    {
        $order->events()->create(['type' => $type, 'message' => $message, 'created_at' => $at]);
    }
}
