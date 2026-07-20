<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequestRequest extends FormRequest
{
    /** @var list<string> */
    public const ALLOWED_PAYMENT_METHOD_TYPES = [
        'card',
        'mbway',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $types = $this->input('payment_method_types');

        if ($types === null && $this->filled('payment_method_type')) {
            $types = [$this->input('payment_method_type')];
        }

        if (! is_array($types)) {
            return;
        }

        $normalized = collect($types)
            ->filter(fn ($type) => is_string($type) && $type !== '')
            ->map(function (string $type): string {
                $type = strtolower(trim($type));

                if (str_starts_with($type, 'card_')) {
                    return 'card';
                }

                if (in_array($type, ['mb_way', 'mb-way', 'mb way'], true)) {
                    return 'mbway';
                }

                return $type;
            })
            ->unique()
            ->values()
            ->all();

        $this->merge(['payment_method_types' => $normalized]);
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
            'payment_method_type' => ['nullable', 'string'],
            'payment_method_types' => ['nullable', 'array', 'min:1'],
            'payment_method_types.*' => ['string', Rule::in(self::ALLOWED_PAYMENT_METHOD_TYPES)],
        ];
    }
}
