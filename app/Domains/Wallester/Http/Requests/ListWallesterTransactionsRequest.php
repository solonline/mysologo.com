<?php

namespace App\Domains\Wallester\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListWallesterTransactionsRequest extends FormRequest
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
            'from_record' => ['sometimes', 'integer', 'min:0'],
            'records_count' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'from_date' => ['sometimes', 'date_format:Y-m-d\TH:i:s\Z'],
            'to_date' => ['sometimes', 'date_format:Y-m-d\TH:i:s\Z', 'after_or_equal:from_date'],
            'card_id' => ['sometimes', 'integer', 'exists:customer_wallester_cards,id'],
        ];
    }
}
