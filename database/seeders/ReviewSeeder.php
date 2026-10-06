<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Ratings skew positive, like most real storefronts.
     */
    private const RATING_WEIGHTS = [5 => 45, 4 => 30, 3 => 15, 2 => 7, 1 => 3];

    private const COMMENTS = [
        5 => [
            'Exactly what I was hoping for. Arrived quickly and works perfectly.',
            'Great quality for the price. I would buy this again.',
            'Better than expected. Packaging was neat and it feels well made.',
            'Love it. Already recommended it to two friends.',
            null,
        ],
        4 => [
            'Really good overall. One small thing I would change, but I am happy with it.',
            'Solid purchase. Does what it says and feels durable.',
            'Very nice. Took a day to get used to, now I use it all the time.',
            null,
        ],
        3 => [
            'It is fine. Does the job, though nothing special.',
            'Decent, but I expected a little more for the price.',
            null,
        ],
        2 => [
            'Not quite what the photos suggested. Usable, but a bit disappointing.',
            'Works, but the finish is not great.',
        ],
        1 => [
            'Did not meet my expectations at all.',
        ],
    ];

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

        $customers = User::where('is_admin', false)->get();
        $products = Product::where('is_active', true)->inRandomOrder()->limit(30)->get();

        if ($customers->isEmpty() || $products->isEmpty()) {
            return;
        }

        foreach ($products as $product) {
            $reviewers = $customers->shuffle()->take(random_int(1, min(4, $customers->count())));

            foreach ($reviewers as $user) {
                $when = now()->subDays(random_int(1, 60))->subMinutes(random_int(0, 1440));

                $shippedAt = $when->copy()->addDay();
                $deliveredAt = $when->copy()->addDays(3);

                $order = Order::create([
                    'user_id' => $user->id,
                    'status' => 'delivered',
                    'total_cents' => $product->price_cents,
                    'tracking_carrier' => 'UPS',
                    'tracking_number' => '1Z'.strtoupper(fake()->bothify('??########')),
                    'shipped_at' => $shippedAt,
                    'delivered_at' => $deliveredAt,
                    'created_at' => $when,
                    'updated_at' => $deliveredAt,
                ]);
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
                    'body' => fake()->randomElement(self::COMMENTS[$rating]),
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
