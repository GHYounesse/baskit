<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
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

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
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
            'movements' => $product->stockMovements()->with('user:id,name')->latest('id')->limit(8)->get(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product, InventoryService $inventory)
    {
        $data = $request->validated();

        // Stock is set separately: under a lock, from the stock on hand right now,
        // with a ledger entry, rather than overwritten with what the form showed.
        $newStock = (int) $data['stock'];
        $stockNote = $data['stock_note'] ?? null;
        unset($data['stock'], $data['stock_note']);

        if ($request->hasFile('image')) {
            $this->deleteStoredImage($product);
            $data['image_path'] = $request->file('image')->store('products', 'public');
        } elseif ($request->boolean('remove_image')) {
            $this->deleteStoredImage($product);
            $data['image_path'] = null;
        }
        unset($data['image'], $data['remove_image']);

        DB::transaction(function () use ($product, $data, $inventory, $newStock, $request, $stockNote) {
            $product->update($data);
            $inventory->setStock($product, $newStock, $request->user(), $stockNote);
        });

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
