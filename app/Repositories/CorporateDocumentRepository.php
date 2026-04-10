<?php

namespace App\Repositories;

use App\Contracts\CorporateDocumentRepositoryInterface;
use App\Models\Customer;
use App\Models\CustomerCorporateDocument;

class CorporateDocumentRepository implements CorporateDocumentRepositoryInterface
{
    public function latestByCustomer(Customer $customer): ?CustomerCorporateDocument
    {
        return $customer->corporateDocuments()->latest()->first();
    }
}
