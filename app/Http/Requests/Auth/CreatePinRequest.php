<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CreatePinRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            'device_token' => ['required', 'string', 'exists:user_devices,device_token'],
            'device_name' => ['required', 'string'],
            'pin' => ['required', 'digits:4'],
        ];
    }
}
