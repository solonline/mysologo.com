<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug') && is_string($this->input('slug'))) {
            $this->merge([
                'slug' => strtolower(trim($this->input('slug'))),
            ]);
        }
    }

    public function rules(): array
    {
        $merchantId = (int) $this->route('merchant');

        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'slug' => [
                'sometimes',
                'string',
                'max:150',
                'alpha_dash',
                Rule::unique('merchants', 'slug')
                    ->ignore($merchantId)
                    ->whereNull('deleted_at'),
            ],
            'webhook_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'mode' => ['prohibited'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $payload = $this->only(['name', 'slug', 'webhook_url']);

            if ($payload === []) {
                $validator->errors()->add(
                    'name',
                    'Indique pelo menos um campo para actualizar: name, slug ou webhook_url.',
                );
            }
        });
    }
}
