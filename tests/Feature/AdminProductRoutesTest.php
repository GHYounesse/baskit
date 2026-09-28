<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_there_is_no_admin_product_show_route(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->get("/admin/products/{$product->id}")
            ->assertStatus(405);
    }
}
