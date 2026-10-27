<?php

namespace App\Domains\Wise\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateWiseTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pin' => ['required', 'digits:4'],
            'device_token' => ['required', 'string'],
            'source_currency' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'source_amount' => ['required', 'integer', 'min:1'],
            'target_amount' => ['nullable', 'integer', 'min:1'],
            'beneficiary_id' => ['required_without:beneficiary', 'prohibits:beneficiary', 'integer', 'min:1'],
            'beneficiary' => ['required_without:beneficiary_id', 'prohibits:beneficiary_id', 'array'],
            'beneficiary.currency' => ['required_with:beneficiary', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'beneficiary.type' => ['required_with:beneficiary', 'string', 'max:50'],
            'beneficiary.account_holder_name' => ['required_with:beneficiary', 'string', 'max:255'],
            'beneficiary.owned_by_customer' => ['required_with:beneficiary', 'boolean'],
            'beneficiary.details' => ['required_with:beneficiary', 'array'],
            'beneficiary.ultimate_beneficiary' => ['sometimes', 'array'],
        ];
    }
}
