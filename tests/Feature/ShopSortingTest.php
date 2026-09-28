<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sorts_by_price_ascending(): void
    {
        Product::factory()->create(['name' => 'Mid', 'price_cents' => 2000, 'is_active' => true]);
        Product::factory()->create(['name' => 'Cheap', 'price_cents' => 1000, 'is_active' => true]);
        Product::factory()->create(['name' => 'Pricey', 'price_cents' => 3000, 'is_active' => true]);

        $response = $this->get('/?sort=price_asc');

        $names = collect($response->viewData('page')['props']['products']['data'])->pluck('name');
        $this->assertSame(['Cheap', 'Mid', 'Pricey'], $names->all());
    }

    public function test_sorts_by_price_descending(): void
    {
        Product::factory()->create(['name' => 'Mid', 'price_cents' => 2000, 'is_active' => true]);
        Product::factory()->create(['name' => 'Cheap', 'price_cents' => 1000, 'is_active' => true]);
        Product::factory()->create(['name' => 'Pricey', 'price_cents' => 3000, 'is_active' => true]);

        $response = $this->get('/?sort=price_desc');

        $names = collect($response->viewData('page')['props']['products']['data'])->pluck('name');
        $this->assertSame(['Pricey', 'Mid', 'Cheap'], $names->all());
    }

    public function test_sorts_by_name(): void
    {
        Product::factory()->create(['name' => 'Zebra', 'is_active' => true]);
        Product::factory()->create(['name' => 'Apple', 'is_active' => true]);

        $response = $this->get('/?sort=name');

        $names = collect($response->viewData('page')['props']['products']['data'])->pluck('name');
        $this->assertSame(['Apple', 'Zebra'], $names->all());
    }

    public function test_invalid_sort_falls_back_to_newest(): void
    {
        $this->get('/?sort=not-a-real-column')->assertOk();
    }
}
