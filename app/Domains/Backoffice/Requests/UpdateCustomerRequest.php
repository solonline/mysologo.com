<?php

namespace App\Domains\Backoffice\Requests;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Kyc\Enums\DocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $customer = $this->route('customer');
        $statusValues = array_column(CustomerLifecycleStatus::cases(), 'value');
        $docStatValues = array_column(DocumentStatus::cases(), 'value');

        return [
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:100', Rule::unique('customers', 'email')->ignore($customer?->id)],
            'currency' => ['nullable', 'string', 'max:3'],
            'language' => ['nullable', 'string', 'max:5'],
            'status' => ['required', 'string', Rule::in($statusValues)],
            'doc_status' => ['nullable', 'string', Rule::in($docStatValues)],
        ];
    }
}
