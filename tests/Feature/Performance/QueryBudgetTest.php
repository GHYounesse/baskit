<?php

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Counts the database queries a page makes.
 */
function queriesFor(string $url): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->get($url)->assertOk();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

// A budget is the current count plus some room. Going over it usually means a query inside a loop
// (an N+1), so fix the loop with `with()` or `withCount()` before raising the number.
dataset('page budgets', [
    'home' => ['/', 25],
    'shop' => ['/shop', 8],
    'shop by category' => ['/shop?category=electronics', 8],
    'shop sorted by rating' => ['/shop?sort=rating', 8],
    'shop search' => ['/shop?q=headphones', 8],
    'product' => ['/products/wireless-headphones', 15],
    'empty cart' => ['/cart', 10],
    'search suggestions' => ['/search/suggest?q=head', 6],
]);

it('keeps each page within its query budget', function (string $url, int $budget) {
    expect(queriesFor($url))->toBeLessThanOrEqual($budget);
})->with('page budgets');

it('makes the same number of queries however many products there are', function (string $url) {
    $before = queriesFor($url);

    Product::factory()->count(30)->create()->each(
        fn (Product $product) => Review::create([
            'product_id' => $product->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 4,
            'body' => 'Good.',
        ])
    );

    expect(queriesFor($url))->toBe($before);
})->with(['/shop', '/shop?sort=rating', '/products/wireless-headphones']);
