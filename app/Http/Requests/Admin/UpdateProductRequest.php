<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'price_cents' => 'required|integer|min:0',
            'stock' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:4096',
            'remove_image' => 'boolean',
        ];
    }
}
