<?php

namespace App\Http\Requests\Stripe;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'             => ['required', 'integer', 'min:1'],
            'payment_method_id'  => ['required', 'integer', 'exists:stripe_payment_method_fees,id'],
            'currency'           => ['sometimes', 'string', 'size:3'],
            'description'        => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
