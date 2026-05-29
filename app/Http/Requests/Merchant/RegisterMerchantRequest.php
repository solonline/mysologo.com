<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class RegisterMerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:150'],
            'mode'               => ['sometimes', 'string', 'in:test,live'],
            'webhook_url'        => ['nullable', 'url', 'max:500'],
            'allowed_currencies' => ['nullable', 'array'],
            'allowed_currencies.*' => ['string', 'size:3'],
        ];
    }
}
