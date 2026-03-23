<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginPinRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'device_token'  => ['required', 'string'],
            'pin'           => ['required', 'digits:4'],
            'email'         => ['required', 'email'],
        ];
    }
}
