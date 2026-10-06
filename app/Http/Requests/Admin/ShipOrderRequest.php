<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ShipOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Admin-only: enforced by the 'admin' route middleware and OrderPolicy::manage.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tracking_carrier' => 'nullable|string|max:40',
            'tracking_number' => 'nullable|string|max:100',
        ];
    }
}
