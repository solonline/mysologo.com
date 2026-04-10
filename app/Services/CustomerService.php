<?php
namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Contracts\CustomerRepositoryInterface;
use App\DTOs\CreateResponsibleDTO;
use App\DTOs\UpdateCustomerDTO;
use App\Models\Customer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CustomerService
{

    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected DiditService $diditService,
        protected KycService $kycService,
        protected RetailCustomerService $retailCustomerService,
        protected CorporateCustomerService $corporateCustomerService
    ) {}
    public function updateOrCreateResponsible(Customer $customer, CreateResponsibleDTO $dto)
    {
        if ($customer->type !== 'corporate') {
            throw new ApiException(
                ErrorCode::CORPORATE_WITHOUT_RESPONSIBLE,
                403,
                'Only corporate customers can have responsible persons.'
            );
        }

        return DB::transaction(function () use ($customer, $dto) {
            $responsible = $customer->responsibles()->first();

            if (!$responsible) {
                $responsible = $this->customerRepository->createResponsible($customer, $dto);
            }

            $this->kycService->ensureValidIndividualDocument($responsible);

            $diditSession = $responsible->diditSessions()->latest()->first();

            return [
                'responsible' => $responsible,
                'didit_session' => $diditSession,
            ];
        });
    }
    public function update(Customer $customer, UpdateCustomerDTO $dto)
    {
        return $this->customerRepository->updateCustomer($customer, $dto);
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


    public function uploadDocument(Customer $customer, UploadedFile $file, string $field, $country): array
    {
        $storagePath = $this->storeFile(
            $customer,
            $file->getRealPath(),
            $field,
            $file->getClientOriginalExtension()
        );

        return $this->finalizeUpload($customer, $field, $storagePath, $country);
    }

    public function uploadDocumentFromPath(Customer $customer, string $localPath, string $field): array
    {
        $storagePath = $this->storeFile($customer, $localPath, $field);

        return $this->finalizeUpload($customer, $field, $storagePath, null);
    }

    private function storeFile(Customer $customer, string $sourcePath, string $field, string $extension = ''): string
    {
        $extension = $extension ?: (pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'bin');
        $filename = uniqid($field . '_') . '.' . $extension;
        $folder = 'customers/' . $customer->id;
        $storageDir = storage_path('app/public/' . $folder);

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        if (!copy($sourcePath, $storageDir . '/' . $filename)) {

            throw new ApiException(
                ErrorCode::FILE_UPLOAD_FAILED,
                500,
                "Falha ao salvar arquivo no storage: {$field}"
            );
        }

        return $folder . '/' . $filename;
    }

    private function finalizeUpload(Customer $customer, string $field, string $storagePath, $country): array
    {
        match ($customer->type) {
            'retail' => $this->retailCustomerService->submitedDocument($customer, $field, $storagePath),
            'corporate' => $this->corporateCustomerService->submitedDocument($customer, $storagePath, $country),
            default => throw new ApiException(
                ErrorCode::FILE_UPLOAD_FAILED,
                400,
                "Tipo de cliente desconhecido: {$customer->type}",
                'customerFinalizeUpload'
            ),
        };

        return [
            'field' => $field,
            'path' => $storagePath,
            'url' => asset('storage/' . $storagePath),
        ];
    }

}
