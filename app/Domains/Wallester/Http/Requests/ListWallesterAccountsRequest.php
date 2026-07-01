<?php

namespace App\Domains\Wallester\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListWallesterAccountsRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'external_id' => ['sometimes', 'string', 'max:255'],
            'is_main' => ['sometimes', 'boolean'],
            'exceeding_credit_limit' => ['sometimes', 'boolean'],
            'order_direction' => ['sometimes', 'string', 'in:asc,desc'],
            'order_field' => ['sometimes', 'string', 'in:created_at,updated_at,balance,blocked_amount,available_amount,name,status'],
            'company_id' => ['sometimes', 'uuid'],
            'person_id' => ['sometimes', 'uuid'],
        ];
    }
}
