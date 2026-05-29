<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'             => ['required', 'integer', 'min:1'],
            'description'        => ['nullable', 'string', 'max:500'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'payer_name'         => ['nullable', 'string', 'max:255'],
            'payer_email'        => ['nullable', 'email', 'max:255'],
            'payer_phone'        => ['nullable', 'string', 'max:50'],
            'callback_url'       => ['nullable', 'url', 'max:500'],
            'metadata'           => ['nullable', 'array'],
            'items'              => ['nullable', 'array'],
            'expires_in'         => ['nullable', 'integer', 'min:5', 'max:1440'],
            'language'           => ['nullable', 'string', 'max:5'],
        ];
    }
}
