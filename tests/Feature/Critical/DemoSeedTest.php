<?php

use App\Models\Product;
use App\Models\Review;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;

it('seeds a complete demo shop and keeps stock in step with its history', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Product::count())->toBe(50)
        ->and(Review::count())->toBeGreaterThan(40)
        ->and(Product::where('stock', 0)->exists())->toBeTrue();

    expect(Artisan::call('inventory:verify'))->toBe(0);
});

it('can be seeded twice without duplicating or overwriting anything', function () {
    $this->seed(DatabaseSeeder::class);
    Product::where('slug', 'wireless-headphones')->update(['stock' => 7]);

    $this->seed(DatabaseSeeder::class);

    expect(Product::count())->toBe(50)
        ->and(Product::where('slug', 'wireless-headphones')->value('stock'))->toBe(7);
});

it('refuses to reset the database unless demo mode is on', function () {
    config(['demo.enabled' => false]);
    Product::factory()->create();

    expect(Artisan::call('demo:reset'))->toBe(1)
        ->and(Product::count())->toBe(1);
});
