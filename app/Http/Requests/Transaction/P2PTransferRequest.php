<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class P2PTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/'],
            'amount'             => ['required', 'integer', 'min:1'],
            'currency'           => ['required', 'string', 'size:3'],
            'description'        => ['nullable', 'string', 'max:255'],
            'device_token'       => ['required', 'string'],
            'pin'                => ['required', 'digits:4'],
        ];
    }
}
