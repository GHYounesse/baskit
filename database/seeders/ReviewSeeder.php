<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ReviewSeeder extends Seeder
{
    /**
     * Ratings skew positive, like most real storefronts.
     */
    private const RATING_WEIGHTS = [5 => 45, 4 => 30, 3 => 15, 2 => 7, 1 => 3];

    /**
     * Demo reviews on a spread of products. Each one is backed by a delivered
     * order, because only customers who bought a product can review it. That
     * means seeding also adds those orders to the admin dashboard totals.
     * Runs once: skipped if any review already exists.
     */
    public function run(): void
    {
        if (Review::exists()) {
            return;
        }

        $comments = require database_path('seeders/data/reviews.php');
        $customers = User::where('is_admin', false)->get();
        $products = Product::with('category')->where('is_active', true)->inRandomOrder()->limit(40)->get();

        if ($customers->isEmpty() || $products->isEmpty()) {
            return;
        }

        foreach ($products as $product) {
            $reviewers = $customers->shuffle()->take(random_int(1, min(4, $customers->count())));

            foreach ($reviewers as $user) {
                $when = now()->subDays(random_int(1, 60))->subMinutes(random_int(0, 1440));

                $shippedAt = $when->copy()->addDay();
                $deliveredAt = $when->copy()->addDays(3);

                // forceFill, because created_at is not mass-assignable: Order::create()
                // would silently stamp every demo order with today's date.
                $order = (new Order)->forceFill([
                    'user_id' => $user->id,
                    'status' => 'delivered',
                    'total_cents' => $product->price_cents,
                    'tracking_carrier' => 'UPS',
                    'tracking_number' => '1Z'.strtoupper(Str::random(10)),
                    'shipped_at' => $shippedAt,
                    'delivered_at' => $deliveredAt,
                    'created_at' => $when,
                    'updated_at' => $deliveredAt,
                ]);
                $order->save();
                // (creating the order already recorded the "placed" event)
                $order->events()->create(['type' => 'shipped', 'message' => 'Shipped with UPS.', 'created_at' => $shippedAt]);
                $order->events()->create(['type' => 'delivered', 'message' => 'Order delivered.', 'created_at' => $deliveredAt]);
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price_cents' => $product->price_cents,
                    'quantity' => 1,
                ]);

                $rating = $this->randomRating();

                Review::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'rating' => $rating,
                    'body' => Arr::random(($comments[$product->category?->slug] ?? $comments['electronics'])[$rating]),
                    // The review comes a few days after the order.
                    'created_at' => $when->copy()->addDays(random_int(2, 7)),
                    'updated_at' => $when->copy()->addDays(random_int(2, 7)),
                ]);
            }
        }
    }

    private function randomRating(): int
    {
        $roll = random_int(1, array_sum(self::RATING_WEIGHTS));

        foreach (self::RATING_WEIGHTS as $rating => $weight) {
            if (($roll -= $weight) <= 0) {
                return $rating;
            }
        }

        return 5;
    }
}
