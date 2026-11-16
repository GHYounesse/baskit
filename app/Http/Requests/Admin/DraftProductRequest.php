<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class DraftProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keywords' => 'nullable|string|max:300|required_without:image',
            'image' => 'nullable|image|max:4096|required_without:keywords',
        ];
    }

    public function messages(): array
    {
        return [
            'keywords.required_without' => 'Add a few keywords or choose a photo first.',
            'image.required_without' => 'Add a few keywords or choose a photo first.',
        ];
    }
}
