<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:200'],
            'username' => ['sometimes', 'string', 'max:40', 'unique:users', 'regex:/^[a-zA-Z0-9_]+$/'],
            'avatar' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:100', 'unique:users'],
            'referal_email_code' => ['nullable', 'string', 'max:255'],
            'email_verified_at' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'referal_sms_code' => ['nullable', 'string', 'max:255'],
            'pin' => ['nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'string', 'min:8'],
            'role' => ['sometimes', 'string', 'in:user,admin'],
        ];
    }
}
