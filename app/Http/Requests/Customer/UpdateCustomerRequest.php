<?php

namespace App\Http\Requests\Customer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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
        if ($this->exists('show_profile_pic')) {
            $this->merge(['show_profile_pic' => $this->boolean('show_profile_pic')]);
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
            'name' => ['sometimes', 'string', 'max:200'],
            'username' => ['sometimes', 'string', 'max:40', 'unique:customers', 'regex:/^[a-zA-Z0-9_]+$/'],
            'avatar' => ['nullable', 'string', 'max:255'],
            'show_profile_pic' => ['sometimes', 'boolean'],
            'email' => ['sometimes', 'string', 'email', 'max:100', 'unique:customers'],
            'referal_email_code' => ['nullable', 'string', 'max:255'],
            'email_verified_at' => ['nullable', 'date'],
            'referal_sms_code' => ['nullable', 'string', 'max:255'],
            'pin' => ['nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'string', 'min:8'],
        ];
    }
}
