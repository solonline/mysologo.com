<?php

namespace App\Http\Requests\Customer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterCustomerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'email', 'max:100', 'unique:customers'],
            'username' => ['sometimes', 'string', 'max:40', 'unique:customers', 'regex:/^[a-zA-Z0-9_]+$/'],
            'password' => ['required_with:username', 'string', 'min:6'],
            'type' => ['required', 'string', 'in:retail,corporate,responsible'],

        ];
    }
}
