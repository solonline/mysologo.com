<?php

namespace App\Domains\Backoffice\Requests;

use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestCustomerBalanceAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super-admin', 'web') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'direction' => ['required', 'string', Rule::enum(CustomerBalanceAdjustmentDirection::class)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:500'],
        ];
    }

    public function amountCents(): int
    {
        return (int) round(((float) $this->validated('amount')) * 100);
    }

    public function direction(): CustomerBalanceAdjustmentDirection
    {
        return CustomerBalanceAdjustmentDirection::from($this->validated('direction'));
    }
}
