<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Product::class);

        return Inertia::render('Admin/Products/Index', [
            'products' => Product::with('category')->latest()->paginate(15),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Admin/Products/Create', ['categories' => Category::all()]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'price_cents' => 'required|integer|min:0',
            'stock' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:4096',
        ]);
        $data['slug'] = Str::slug($data['name']).'-'.uniqid();

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }
        unset($data['image']);

        Product::create($data);

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product)
    {
        $this->authorize('update', $product);

        return Inertia::render('Admin/Products/Edit', [
            'product' => $product,
            'categories' => Category::all(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'price_cents' => 'required|integer|min:0',
            'stock' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:4096',
            'remove_image' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $this->deleteStoredImage($product);
            $data['image_path'] = $request->file('image')->store('products', 'public');
        } elseif ($request->boolean('remove_image')) {
            $this->deleteStoredImage($product);
            $data['image_path'] = null;
        }
        unset($data['image'], $data['remove_image']);

        $product->update($data);

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $this->deleteStoredImage($product);

        $product->delete();

        return back()->with('success', 'Product deleted.');
    }

    /**
     * Delete the product's uploaded image file, if it has one. Seeded demo
     * products point at an external stock photo URL, not a local file, so
     * those are left alone.
     */
    private function deleteStoredImage(Product $product): void
    {
        if ($product->image_path && ! str_starts_with($product->image_path, 'http')) {
            Storage::disk('public')->delete($product->image_path);
        }
    }
}
