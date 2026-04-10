<?php

namespace App\Contracts;

use App\Models\Customer;
use App\Models\CustomerCorporateDocument;

interface CorporateDocumentRepositoryInterface
{
    public function latestByCustomer(Customer $customer): ?CustomerCorporateDocument;
}
