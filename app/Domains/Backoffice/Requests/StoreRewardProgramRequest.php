<?php

namespace App\Domains\Backoffice\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRewardProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:reward_programs,slug'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', Rule::when($this->filled('valid_from'), ['after_or_equal:valid_from'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.required' => 'Indica o código do programa.',
            'slug.regex' => 'O código deve começar por uma letra e usar só minúsculas, números e underscore.',
            'slug.unique' => 'Já existe um programa com este código.',
            'name.required' => 'Indica o nome do programa.',
            'valid_until.after_or_equal' => 'A validade final tem de ser igual ou posterior ao início.',
        ];
    }
}
