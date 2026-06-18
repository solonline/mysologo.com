<?php

namespace App\Domains\Backoffice\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTpaPaymentMethodFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payment_method' => 'required|string|max:50',
            'label' => 'required|string|max:100',
            'fee_percentage' => 'required|numeric|min:0|max:1',
            'fee_fixed' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'is_active' => 'boolean',
        ];
    }
}
