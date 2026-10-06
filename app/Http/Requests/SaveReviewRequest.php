<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Who may review (or edit) is decided by ReviewPolicy in the controller.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => 'required|integer|between:1,5',
            'body' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'Choose a star rating.',
            'rating.between' => 'Choose a rating from 1 to 5 stars.',
            'body.max' => 'Keep your review under 2,000 characters.',
        ];
    }
}
