<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_cart_suggests_products_that_can_be_bought(): void
    {
        $available = Product::factory()->create(['category_id' => null, 'stock' => 5, 'is_active' => true]);
        Product::factory()->create(['category_id' => null, 'stock' => 0, 'is_active' => true]);
        Product::factory()->create(['category_id' => null, 'stock' => 5, 'is_active' => false]);

        $this->get('/cart')->assertInertia(fn ($page) => $page
            ->has('suggestions', 1)
            ->where('suggestions.0.id', $available->id));
    }

    public function test_a_cart_with_items_does_not_load_suggestions(): void
    {
        $product = Product::factory()->create(['category_id' => null, 'stock' => 5]);
        Product::factory()->create(['category_id' => null, 'stock' => 5]);

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $this->get('/cart')->assertInertia(fn ($page) => $page->has('suggestions', 0));
    }
}
