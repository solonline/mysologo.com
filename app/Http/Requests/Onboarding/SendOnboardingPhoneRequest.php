<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class SendOnboardingPhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('phone')) {
            $digits = preg_replace('/\D+/', '', (string) $this->input('phone')) ?: null;

            $this->merge(['phone' => $digits]);
        }

        if ($this->exists('whatsapp')) {
            $this->merge([
                'whatsapp' => filter_var($this->input('whatsapp'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'whatsapp' => ['sometimes', 'boolean'],
        ];
    }
}
