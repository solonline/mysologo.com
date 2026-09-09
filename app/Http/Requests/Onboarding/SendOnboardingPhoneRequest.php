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
        if (! $this->exists('phone')) {
            return;
        }

        $digits = preg_replace('/\D+/', '', (string) $this->input('phone')) ?: null;

        $this->merge(['phone' => $digits]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
        ];
    }
}
