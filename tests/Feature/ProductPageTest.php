<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_includes_related_products_from_the_same_category(): void
    {
        $category = Category::create(['name' => 'Fruit', 'slug' => 'fruit']);
        $other = Category::create(['name' => 'Dairy', 'slug' => 'dairy']);

        $product = Product::factory()->create(['category_id' => $category->id]);
        $sibling = Product::factory()->create(['category_id' => $category->id]);
        Product::factory()->create(['category_id' => $other->id]);

        $related = $this->get("/products/{$product->slug}")
            ->assertOk()
            ->viewData('page')['props']['related'];

        $this->assertCount(1, $related);
        $this->assertSame($sibling->id, $related[0]['id']);
    }

    public function test_inactive_product_is_hidden_from_customers_but_visible_to_admins(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $this->get("/products/{$product->slug}")->assertNotFound();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get("/products/{$product->slug}")->assertOk();
    }
}
