<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class ApplyCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Guests can try a code on their cart too.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:32',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Enter a discount code.',
        ];
    }
}
