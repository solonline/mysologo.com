<?php
namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class CustomerService
{
    public function __construct(protected DiditService $diditService, protected KycService $kycService, protected RetailCustomerService $retailCustomerService)
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

    public function sendValidationEmail(Customer $customer, string $validationUrl)
    {
        \Mail::to($customer->email)->send(new \App\Mail\ValidationEmail($customer, $validationUrl));
    }

    public function generateEmailValidationUrl(Customer $customer): string
    {
        return \URL::temporarySignedRoute(
            'email.validated',
            now()->addMinutes(60),
            [
                'id' => $customer->id,
                'hash' => sha1($customer->email),
                'redirect' => env('URL_FRONTEND') . '/email-validated',
                'name' => $customer->name,
            ]
        );
    }

    public function uploadDocument(Customer $customer, array $data)
    {
        try {
            // Diretório baseado no tipo já validado
            $directory = $customer->type === 'retail'
                ? 'customers/' . $customer->id . '/documents'
                : 'corporate_customers/' . $customer->id . '/documents';

            $filePath = $data['file']->store($directory, 'local');

            if (!$filePath) {
                throw new ApiException(
                    ErrorCode::FILE_UPLOAD_FAILED,
                    500,
                    'Failed to store the file.',
                    'customerUploadDocument'
                );
            }

            return [
                'url' => \Storage::disk('local')->url($filePath),
                'path' => $filePath,
            ];
        } catch (\Exception $e) {
            throw new ApiException(
                ErrorCode::FILE_UPLOAD_FAILED,
                500,
                'Unexpected error during file upload.',
                'customerUploadDocument',
                $e
            );
        }
    }
}
