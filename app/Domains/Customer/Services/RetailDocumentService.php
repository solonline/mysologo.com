<?php

namespace App\Domains\Customer\Services;

use App\Models\Customer;

class RetailDocumentService
{
    public function submittedDocument(Customer $customer, string $filePath): void
    {
        // Lógica específica para clientes retail (extensível).
    }
}
