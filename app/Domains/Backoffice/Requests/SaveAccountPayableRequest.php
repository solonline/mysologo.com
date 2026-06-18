<?php

namespace App\Domains\Backoffice\Requests;

use App\Domains\Backoffice\Queries\GetAcceptedCurrenciesQuery;
use App\Models\AccountPayable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAccountPayableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $currencies = app(GetAcceptedCurrenciesQuery::class)->execute();

        return [
            'supplier' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issued_at' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'currency' => ['required', 'string', 'size:3', Rule::in($currencies)],
            'status' => ['required', Rule::in(AccountPayable::statuses())],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'is_recurring' => ['boolean'],
            'recurrence_interval' => ['nullable', Rule::in(AccountPayable::recurrenceIntervals())],
            'recurrence_ends_at' => ['nullable', 'date', 'after_or_equal:due_date'],
            'recurrence_occurrences' => ['nullable', 'integer', 'min:1', 'max:240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('is_recurring')) {
                return;
            }

            if ($this->route('accountPayable') !== null) {
                return;
            }

            if (! $this->filled('recurrence_interval')) {
                $validator->errors()->add('recurrence_interval', 'Escolha o intervalo da recorrência.');
            }

            if (! $this->filled('recurrence_ends_at') && ! $this->filled('recurrence_occurrences')) {
                $validator->errors()->add('recurrence_ends_at', 'Informe uma data final ou número de ocorrências.');
            }
        });
    }

    /** @return array<string, mixed> */
    public function accountPayableAttributes(): array
    {
        $data = $this->validated();
        $data['is_recurring'] = $this->boolean('is_recurring');

        if (! $data['is_recurring']) {
            $data['recurrence_interval'] = null;
            $data['recurrence_ends_at'] = null;
            $data['recurrence_occurrences'] = null;
            $data['recurrence_group_uuid'] = null;
            $data['occurrence_index'] = 1;
            $data['occurrence_total'] = 1;
        }

        if ($data['status'] !== AccountPayable::STATUS_PAID) {
            $data['paid_at'] = null;
        }

        $data['currency'] = strtoupper($data['currency']);

        return $data;
    }
}
