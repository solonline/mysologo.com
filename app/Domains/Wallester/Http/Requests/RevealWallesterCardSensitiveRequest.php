<?php

namespace App\Domains\Wallester\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RevealWallesterCardSensitiveRequest extends FormRequest
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
            'password' => ['required_without:pin', 'string', 'prohibits:pin,device_token'],
            'pin' => ['required_without:password', 'digits:4', 'prohibits:password'],
            'device_token' => ['required_with:pin', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required_without' => 'Provide your account password or device PIN.',
            'pin.required_without' => 'Provide your account password or device PIN.',
            'device_token.required_with' => 'Device token is required when using PIN.',
        ];
    }
}
