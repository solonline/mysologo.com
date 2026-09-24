<?php

namespace App\Domains\Push\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DisablePushDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'device_token' => ['required', 'string'],
        ];
    }
}
