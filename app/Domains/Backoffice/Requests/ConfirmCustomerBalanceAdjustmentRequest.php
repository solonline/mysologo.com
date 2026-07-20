<?php

namespace App\Domains\Backoffice\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmCustomerBalanceAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super-admin', 'web') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
