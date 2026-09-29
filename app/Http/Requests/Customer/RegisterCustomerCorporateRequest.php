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

    protected function prepareForValidation(): void
    {
        if ($this->filled('referral_code')) {
            $this->merge([
                'referral_code' => strtolower(str_replace(' ', '_', (string) $this->input('referral_code'))),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:200'],
            'username' => ['required', 'string', 'max:100', 'unique:customers,username'],
            'email' => ['required', 'string', 'email', 'max:100', 'unique:customers'],
            'tax_id' => ['required', 'string', 'max:50', 'unique:customer_corporate_documents,tax_id'],
            'password' => ['required', 'string', 'min:6'],
            'country_code' => ['required', 'string', 'size:2'],
            'referral_code' => ['nullable', 'string', 'max:40', 'exists:customers,username'],
        ];
    }
}
