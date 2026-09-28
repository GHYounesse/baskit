<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductImageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_create_product_with_image(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('product.jpg');

        $this->actingAs($this->admin())->post('/admin/products', [
            'name' => 'Red Widget',
            'price_cents' => 1000,
            'stock' => 5,
            'is_active' => true,
            'image' => $image,
        ])->assertRedirect('/admin/products');

        $product = Product::firstOrFail();
        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
        $this->assertStringContainsString('/storage/', $product->image_url);
    }

    public function test_replacing_image_deletes_the_old_one(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create(['image_path' => 'products/old.jpg']);
        Storage::disk('public')->put('products/old.jpg', 'fake');

        $this->actingAs($this->admin())->put("/admin/products/{$product->id}", [
            'name' => $product->name,
            'price_cents' => $product->price_cents,
            'stock' => $product->stock,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('new.jpg'),
        ])->assertRedirect('/admin/products');

        Storage::disk('public')->assertMissing('products/old.jpg');
        $this->assertNotSame('products/old.jpg', $product->fresh()->image_path);
    }

    public function test_removing_image_clears_it(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create(['image_path' => 'products/old.jpg']);
        Storage::disk('public')->put('products/old.jpg', 'fake');

        $this->actingAs($this->admin())->put("/admin/products/{$product->id}", [
            'name' => $product->name,
            'price_cents' => $product->price_cents,
            'stock' => $product->stock,
            'is_active' => true,
            'remove_image' => true,
        ])->assertRedirect('/admin/products');

        Storage::disk('public')->assertMissing('products/old.jpg');
        $this->assertNull($product->fresh()->image_path);
    }

    public function test_deleting_product_deletes_its_image(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create(['image_path' => 'products/gone.jpg']);
        Storage::disk('public')->put('products/gone.jpg', 'fake');

        $this->actingAs($this->admin())->delete("/admin/products/{$product->id}")->assertRedirect();

        Storage::disk('public')->assertMissing('products/gone.jpg');
    }

    public function test_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post('/admin/products', [
            'name' => 'Bad Upload',
            'price_cents' => 1000,
            'stock' => 5,
            'image' => UploadedFile::fake()->create('doc.pdf', 100),
        ])->assertSessionHasErrors('image');
    }

    public function test_external_stock_photo_url_is_used_as_is(): void
    {
        $product = Product::factory()->create(['image_path' => 'https://picsum.photos/seed/demo/600/600']);

        $this->assertSame('https://picsum.photos/seed/demo/600/600', $product->image_url);
    }

    public function test_deleting_product_with_external_image_does_not_touch_storage(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create(['image_path' => 'https://picsum.photos/seed/demo/600/600']);

        $this->actingAs($this->admin())->delete("/admin/products/{$product->id}")->assertRedirect();

        $this->assertSame(0, count(Storage::disk('public')->allFiles()));
    }
}
