<?php

namespace App\Domains\Customer\Services;

use App\Domains\Kyc\Enums\DocumentStatus;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerCorporateDocument;

class CorporateDocumentService
{
    public function initiateKyBReview(Customer $customer): CustomerCorporateDocument
    {
        if ($customer->type !== 'corporate') {
            throw new ApiException(
                ErrorCode::CORPORATE_DOCUMENT_INVALID,
                400,
                'KYB review can only be initiated for corporate customers.',
            );
        }

        $document = CustomerCorporateDocument::query()->firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'legal_name' => $customer->name ?: $customer->username,
                'country_code' => 'PT',
                'tax_id' => 'kyb_'.$customer->id.'_'.uniqid(),
                'document_type' => 'BUSINESS_ID',
                'doc_status' => DocumentStatus::ANALYSING->value,
                'status' => 'Pending',
            ],
        );

        if ($document->doc_status !== DocumentStatus::APPROVED->value) {
            $document->doc_status = DocumentStatus::ANALYSING->value;
            $document->status = 'Pending';
            $document->save();
        }

        $customer->doc_status = DocumentStatus::ANALYSING->value;
        $customer->save();

        return $document;
    }
}
