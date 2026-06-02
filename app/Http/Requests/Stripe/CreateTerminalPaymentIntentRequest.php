<?php

namespace App\Http\Requests\Stripe;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTerminalPaymentIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tpa_serial_number' => [
                'required',
                'string',
                Rule::exists('tpas', 'serial')
                    ->where('customer_id', $this->attributes->get('merchant')->customer_id)
                    ->where('status', 'active'),
            ],
            'amount'   => ['required', 'integer', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ];
    }

    public function messages(): array
    {
        return [
            'tpa_serial_number.exists' => 'The serial number is invalid or the TPA is not active.',
        ];
    }
}
