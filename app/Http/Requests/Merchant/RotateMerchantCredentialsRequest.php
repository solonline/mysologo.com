<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class RotateMerchantCredentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'api_key' => ['sometimes', 'boolean'],
            'webhook_secret' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $rotateApiKey = $this->boolean('api_key');
            $rotateWebhookSecret = $this->boolean('webhook_secret');

            if ($rotateApiKey === $rotateWebhookSecret) {
                $validator->errors()->add(
                    'api_key',
                    'Indique exactamente um: api_key ou webhook_secret como true.',
                );
            }
        });
    }
}
