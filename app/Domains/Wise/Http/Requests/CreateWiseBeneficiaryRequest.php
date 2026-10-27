<?php

namespace App\Domains\Wise\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateWiseBeneficiaryRequest extends FormRequest
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
            'wise_recipient_id' => ['sometimes', 'integer', 'min:1'],
            'currency' => ['required_without:wise_recipient_id', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'type' => ['required_without:wise_recipient_id', 'string', 'max:50'],
            'account_holder_name' => ['required_without:wise_recipient_id', 'string', 'max:255'],
            'owned_by_customer' => ['required_without:wise_recipient_id', 'boolean'],
            'details' => ['required_without:wise_recipient_id', 'array'],
            'ultimate_beneficiary' => ['sometimes', 'array'],
        ];
    }
}
