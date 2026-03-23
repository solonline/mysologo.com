<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterUserRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:100', 'unique:users'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'username' => ['required', 'string', 'max:40', 'unique:users', 'regex:/^[a-zA-Z0-9_]+$/'],
            'password' => ['sometimes', 'string', 'min:6'],
            'role' => ['required', 'string', 'in:admin,retail_customer,corporate_customer'],
        ];
    }
}
