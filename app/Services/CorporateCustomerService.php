<?php
namespace App\Services;

use App\Enums\DocumentStatus;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerCorporateDocument;

class CorporateCustomerService
{
   public function submitedDocument(Customer $customer, $filePath, $country)
    {
        try {
            // Define o tipo de documento conforme o país
            $documentType = match ($country) {
                'BR' => 'CNPJ',
                'US' => 'EIN',
                'PT' => 'NIF',
                'EN' => 'Company Registration Number',
                default => 'BUSINESS_ID',
            };

            $document = CustomerCorporateDocument::firstOrCreate([
                'customer_id' => $customer->id,
                'legal_name' => $customer->username,
                'document_type' => $documentType,
                'country_code' => $country,
                'tax_id' => $customer->username . '_tax_' . uniqid(), // Placeholder, deve ser extraído do documento real
                'doc_status' => DocumentStatus::ANALYSIS,
            ]);

            $document->file_path = $filePath;
            $document->save();

            $customer->doc_status = 'analysing';
            $customer->save();

            return $document;
        } catch (\Exception $e) {
            throw new ApiException(
                ErrorCode::FILE_UPLOAD_FAILED,
                400,
                'Failed to process corporate document.',
                'corporateDocumentUpload',
                $e
            );
        }
    }
}
