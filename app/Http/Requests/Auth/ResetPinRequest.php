<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ResetPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_token' => ['required', 'string'],
            'pin' => ['required', 'digits:4', Rule::when(
                $this->filled('current_pin'),
                ['different:current_pin'],
            )],
            'current_pin' => ['required_without:password', 'digits:4', 'prohibits:password'],
            'password' => ['required_without:current_pin', 'string', 'prohibits:current_pin'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required_without' => 'Provide your account password or current PIN.',
            'current_pin.required_without' => 'Provide your account password or current PIN.',
            'pin.different' => 'The new PIN must be different from the current PIN.',
        ];
    }
}
