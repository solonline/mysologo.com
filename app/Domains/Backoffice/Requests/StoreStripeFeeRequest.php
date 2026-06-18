<?php

namespace App\Domains\Backoffice\Requests;

use App\Domains\Backoffice\Queries\GetAcceptedCurrenciesQuery;
use Illuminate\Validation\Rule;

class StoreStripeFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $currencies = app(GetAcceptedCurrenciesQuery::class)->execute();

        return [
            'payment_method' => [
                'required', 'string', 'max:100',
                Rule::unique('stripe_payment_method_fees')->where('currency', $this->input('currency')),
            ],
            'label' => ['required', 'string', 'max:150'],
            'stripe_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stripe_fee_fixed' => ['nullable', 'numeric', 'min:0'],
            'platform_fee_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'platform_fee_fixed' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', Rule::in($currencies)],
            'is_active' => ['boolean'],
        ];
    }
}
