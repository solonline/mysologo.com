<?php

namespace App\Http\Requests\Customer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterCustomerCorporateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:200'],
            'trading_name' => ['nullable', 'string', 'max:100', 'unique:customers,username'],
            'email' => ['required', 'string', 'email', 'max:100', 'unique:customers'],
            'tax_id' => ['required', 'string', 'max:50', 'unique:customer_corporate_documents,tax_id'],
            'password' => ['required', 'string', 'min:6'],
            'country_code' => ['required', 'string', 'size:2'],
        ];
    }
}
