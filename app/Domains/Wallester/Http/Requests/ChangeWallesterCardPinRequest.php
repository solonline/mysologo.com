<?php

namespace App\Domains\Wallester\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChangeWallesterCardPinRequest extends FormRequest
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
            'pin' => ['required', 'digits:4'],
            'device_token' => ['required', 'string'],
            'pin_card' => ['required', 'digits:4'],
            'confirm_pin_card' => ['required', 'digits:4', 'same:pin_card'],
        ];
    }

    public function messages(): array
    {
        return [
            'pin_card.required' => 'The pin card is required.',
            'pin_card.digits' => 'The pin card must be 4 digits.',
        ];
    }
}
