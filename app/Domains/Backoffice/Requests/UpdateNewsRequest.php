<?php

namespace App\Domains\Backoffice\Requests;

use App\Models\News;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
            'status' => ['required', 'string', Rule::in(News::STATUSES)],
            'audience' => ['required', 'string', Rule::in(News::AUDIENCES)],
            'pinned' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', Rule::when($this->filled('published_at'), ['after:published_at'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Indica o título da notícia.',
            'body.required' => 'Indica o conteúdo da notícia.',
            'image.image' => 'A capa tem de ser uma imagem.',
            'image.mimes' => 'A capa deve ser JPG, PNG ou WebP.',
            'image.max' => 'A capa não pode ultrapassar 5 MB.',
            'expires_at.after' => 'A data de expiração tem de ser posterior à publicação.',
        ];
    }
}
