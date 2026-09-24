<?php

namespace App\Domains\Push\Http\Requests;

use App\Domains\Push\Enums\PushPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterPushDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'device_token' => ['required', 'string'],
            'platform' => ['required', Rule::enum(PushPlatform::class)],
            'push_token' => ['nullable', 'string', 'max:512'],
        ];
    }
}
