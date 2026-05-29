<?php

namespace App\Http\Requests\Tpa;

use Illuminate\Foundation\Http\FormRequest;

class LinkTpaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'      => ['required', 'email'],
            'password'   => ['required', 'string'],
            'tpa_serial' => ['required', 'string', 'unique:tpas,serial'],
        ];
    }
}
