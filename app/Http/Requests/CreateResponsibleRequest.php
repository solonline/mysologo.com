<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateResponsibleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Busca o responsável já vinculado à empresa (se houver)
        $responsible = $this->user()->responsibles()->first();
        $responsibleId = $responsible?->id ?? null;
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:200', 'unique:customers,username' . ($responsibleId ? ",{$responsibleId}" : '')],
            'email' => [
                'required',
                'email',
                'max:100',
                'unique:customers,email' . ($responsibleId ? ",{$responsibleId}" : '')
            ],
        ];
    }
}
