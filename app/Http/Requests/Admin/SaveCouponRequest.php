<?php

namespace App\Http\Requests\Admin;

use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create and update share the same rules; on update the code may keep
 * its own value.
 */
class SaveCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        $coupon = $this->route('coupon');

        return $coupon
            ? $this->user()->can('update', $coupon)
            : $this->user()->can('create', Coupon::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->code)) {
            $this->merge(['code' => strtoupper(trim($this->code))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:32', 'alpha_dash',
                Rule::unique('coupons', 'code')->ignore($this->route('coupon')),
            ],
            'type' => ['required', Rule::in([Coupon::TYPE_PERCENT, Coupon::TYPE_FIXED])],
            // Percent: 1-100. Fixed: cents.
            'value' => [
                'required', 'integer', 'min:1',
                Rule::when($this->input('type') === Coupon::TYPE_PERCENT, 'max:100'),
            ],
            'min_subtotal_cents' => 'nullable|integer|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.alpha_dash' => 'Use letters, numbers, dashes and underscores only.',
            'code.unique' => 'A coupon with this code already exists.',
            'value.max' => 'A percentage can be at most 100.',
        ];
    }
}
