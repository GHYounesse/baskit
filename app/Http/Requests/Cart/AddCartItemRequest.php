<?php

namespace App\Http\Requests\Cart;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AddCartItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Guests can add to a cart too.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            // Must be one of this product's own variants.
            'variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $this->input('product_id'))],
            'quantity' => 'required|integer|min:1',
        ];
    }

    /**
     * A product that comes in sizes or colours can't be added without choosing one.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $product = Product::find($this->input('product_id'));

            if ($product && $product->hasVariants() && ! $this->filled('variant_id')) {
                $validator->errors()->add('variant_id', 'Choose an option first.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'variant_id.exists' => "That option isn't available for this product.",
        ];
    }
}
