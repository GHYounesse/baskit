<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\VariantService;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    public function __construct(private VariantService $variants) {}

    public function store(SaveVariantRequest $request, Product $product)
    {
        $variant = $this->variants->create($product, $request->validated(), $request->user());

        return back()->with('success', "Variant {$variant->label} added.");
    }

    public function update(SaveVariantRequest $request, ProductVariant $variant)
    {
        $data = $request->validated();

        $this->variants->update($variant, $data, $request->user(), $data['stock_note'] ?? null);

        return back()->with('success', 'Variant updated.');
    }

    public function destroy(Request $request, ProductVariant $variant)
    {
        $this->authorize('update', $variant->product);

        $label = $variant->label;
        $this->variants->delete($variant, $request->user());

        return back()->with('success', "Variant {$label} removed.");
    }
}
