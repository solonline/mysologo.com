<?php
namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CustomerService
{
    public function __construct(protected DiditService $diditService, protected KycService $kycService)
    {

    }
    public function updateOrCreateResponsible(Customer $customer, array $data)
    {
        if ($customer->type !== 'corporate') {
            throw new ApiException(
                ErrorCode::CORPORATE_WITHOUT_RESPONSIBLE,
                403,
                'Only corporate customers can have responsible persons.'
            );
        }

        return DB::transaction(function () use ($customer, $data) {

            // 🔍 tenta buscar responsável existente
            $responsible = $customer->responsibles()->first();

            // 🆕 se não existe, cria
            if (!$responsible) {
                $data['username'] = $customer->username . '_responsible_' . uniqid();

                $responsible = Customer::create([
                    ...$data,
                    'corporate_id' => $customer->id, // ⚠️ padroniza nome
                    'type' => 'responsible',
                ]);
            }

            // 🔁 garante sessão Didit válida
            $this->kycService->ensureValidIndividualDocument($responsible);

            $diditSession = $responsible->diditSessions()->latest()->first();

            return [
                'responsible' => $responsible,
                'didit_session' => $diditSession,
            ];
        });
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
