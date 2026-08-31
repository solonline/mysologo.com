<?php

namespace App\Domains\Backoffice\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge([
                'username' => strtolower(str_replace(' ', '_', (string) $this->input('username'))),
            ]);
        }

        if ($this->has('currency')) {
            $this->merge([
                'currency' => strtoupper((string) $this->input('currency')),
            ]);
        }

        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/\D+/', '', (string) $this->input('phone')) ?: $this->input('phone'),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'username' => ['required', 'string', 'max:40', 'unique:customers,username', 'regex:/^[a-zA-Z0-9_]+$/'],
            'email' => ['required', 'email', 'max:100', 'unique:customers,email'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'password' => ['required', Password::defaults(), 'confirmed'],
            'type' => ['required', 'string', Rule::in(['retail', 'corporate'])],
            'language' => ['required', 'string', 'max:5'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }
}
