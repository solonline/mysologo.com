<?php

namespace App\Domains\Backoffice\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkCustomerWallesterCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web')?->can('customers.edit') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'card_id' => ['required', 'integer', 'exists:customer_wallester_cards,id'],
            'target_customer' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'card_id.required' => 'Selecione o cartão a transferir.',
            'target_customer.required' => 'Indique o username do cliente de destino.',
        ];
    }
}
