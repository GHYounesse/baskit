<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create and update share the same rules; on update the variant may keep its
 * own SKU and options.
 */
class SaveVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $variant = $this->route('variant');

        return $variant
            ? $this->user()->can('update', $variant->product)
            : $this->user()->can('update', $this->route('product'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'size' => trim((string) $this->input('size')),
            'color' => trim((string) $this->input('color')),
            'sku' => strtoupper(trim((string) $this->input('sku'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'size' => 'nullable|string|max:40',
            'color' => 'nullable|string|max:40',
            // Optional: left blank, one is generated.
            'sku' => ['nullable', 'string', 'max:64', 'regex:/^[A-Z0-9._-]+$/', Rule::unique('product_variants', 'sku')->ignore($this->route('variant'))],
            'stock' => 'required|integer|min:0',
            'stock_note' => 'nullable|string|max:255',
        ];
    }

    /**
     * A variant needs at least one option, and no two variants of a product
     * may have the same options.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('size') === '' && $this->input('color') === '') {
                $validator->errors()->add('size', 'Enter a size, a colour, or both.');

                return;
            }

            $variant = $this->route('variant');
            $product = $variant ? $variant->product : $this->route('product');

            $duplicate = ProductVariant::query()
                ->where('product_id', $product instanceof Product ? $product->id : $product)
                ->whereRaw('LOWER(size) = ?', [mb_strtolower($this->input('size'))])
                ->whereRaw('LOWER(color) = ?', [mb_strtolower($this->input('color'))])
                ->when($variant, fn ($q) => $q->whereKeyNot($variant->id))
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('size', 'This product already has a variant with that size and colour.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'sku.regex' => 'Use letters, numbers, dots, dashes and underscores only.',
            'sku.unique' => 'Another variant already uses this SKU.',
        ];
    }
}
