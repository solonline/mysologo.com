<?php

namespace App\Domains\Customer\Services;

use App\Domains\Customer\Contracts\CustomerRepositoryInterface;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use Illuminate\Http\UploadedFile;

class CustomerDocumentService
{
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected RetailDocumentService $retailDocuments,
    ) {}

    public function uploadAvatar(Customer $customer, UploadedFile $file): Customer
    {
        $path = $this->storeFile(
            $customer,
            $file->getRealPath(),
            'avatar',
            $file->getClientOriginalExtension(),
        );

        return $this->customerRepository->updateAvatar($customer, $path);
    }

    /** @return array{field: string, path: string, url: string} */
    public function uploadDocument(Customer $customer, UploadedFile $file, string $field, ?string $country): array
    {
        $storagePath = $this->storeFile(
            $customer,
            $file->getRealPath(),
            $field,
            $file->getClientOriginalExtension(),
        );

        return $this->finalizeUpload($customer, $field, $storagePath);
    }

    /** @return array{field: string, path: string, url: string} */
    public function uploadDocumentFromPath(Customer $customer, string $localPath, string $field): array
    {
        $storagePath = $this->storeFile($customer, $localPath, $field);

        return $this->finalizeUpload($customer, $field, $storagePath);
    }

    private function storeFile(Customer $customer, string $sourcePath, string $field, string $extension = ''): string
    {
        $extension = $extension ?: (pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'bin');
        $filename = uniqid($field.'_').'.'.$extension;
        $folder = 'customers/'.$customer->id;
        $storageDir = storage_path('app/public/'.$folder);

        if (! is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        if (! copy($sourcePath, $storageDir.'/'.$filename)) {
            throw new ApiException(
                ErrorCode::FILE_UPLOAD_FAILED,
                500,
                "Falha ao salvar arquivo no storage: {$field}"
            );
        }

        return $folder.'/'.$filename;
    }

    /** @return array{field: string, path: string, url: string} */
    private function finalizeUpload(Customer $customer, string $field, string $storagePath): array
    {
        match ($customer->type) {
            'retail' => $this->retailDocuments->submittedDocument($customer, $storagePath),
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
            'url' => asset('storage/'.$storagePath),
        ];
    }
}
