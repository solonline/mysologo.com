<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreatePinRequest extends FormRequest
{
    public function authorize()
    {
        return Auth::check();
    }

    public function rules()
    {
        return [
            'device_token' => ['required', 'string', 'unique:customer_devices,device_token'],
            'device_name' => ['required', 'string'],
            'pin' => ['required', 'digits:4'],
        ];
    }
}
