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
            'delivery_address' => ['required_if:type,Physical', 'array'],
            'delivery_address.address' => ['required_if:type,Physical', 'string', 'max:255'],
            'delivery_address.city' => ['required_if:type,Physical', 'string', 'max:255'],
            'delivery_address.state' => ['required_if:type,Physical', 'string', 'max:255'],
            'delivery_address.country' => ['required_if:type,Physical', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'delivery_address.first_name' => ['required_if:type,Physical', 'string', 'max:255'],
            'delivery_address.last_name' => ['required_if:type,Physical', 'string', 'max:255'],
            'delivery_address.phone' => ['required_if:type,Physical', 'string', 'max:255'],
            'delivery_address.postal_code' => ['required_if:type,Physical', 'string', 'max:255'],
        ];
    }
}
