<?php

namespace App\Domains\Wallester\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateWallesterCardRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:Virtual,Physical'],
            'name' => ['required', 'string', 'max:255'],
            'limits' => ['sometimes', 'array'],
            'security' => ['sometimes', 'array'],
            'pin' => ['required', 'digits:4'],
            'device_token' => ['required', 'string'],
        ];
    }
}
