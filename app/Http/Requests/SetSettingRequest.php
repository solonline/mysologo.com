<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'value' => ['required'],
        ];
    }
}
