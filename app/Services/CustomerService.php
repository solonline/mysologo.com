<?php
namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CustomerService
{
    public function __construct(protected DiditService $diditService)
    {

    }

    public function createResponsible(Customer $customer, array $data)
    {
        if($customer->type != 'corporate') {
            throw new ApiException(
                ErrorCode::CORPORATE_WITHOUT_RESPONSIBLE,
                403,
                'Only corporate customers can have responsible persons.'
            );
        }

        return DB::transaction(function () use ($customer, $data) {

        $responsible = Customer::create([
            ...$data,
            'corporate_id' => $customer->id,
            'type' => 'responsible',
        ]);

        $diditSession = $this->diditService->createSession($responsible);

        return [
            'responsible' => $responsible,
            'didit_session' => $diditSession,
        ];
    });
        // Lógica para criar um responsável vinculado à empresa do usuário autenticado
    }
    public function update(array $data)
    {
        $customer = auth()->user();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $customer->update($data);
        return $customer;
    }
}
