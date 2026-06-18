<?php

namespace App\Domains\Backoffice\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateStaffUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:200'],
            'username' => ['required', 'string', 'max:40', 'unique:users,username,' . $user?->id],
            'email' => ['required', 'email', 'max:100', 'unique:users,email,' . $user?->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', Password::defaults(), 'confirmed'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
        ];
    }
}
