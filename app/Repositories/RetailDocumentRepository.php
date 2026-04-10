<?php

namespace App\Repositories;

use App\Contracts\RetailDocumentRepositoryInterface;
use App\Models\CustomerRetailDocument;

class RetailDocumentRepository implements RetailDocumentRepositoryInterface
{
    public function firstOrCreateByDocumentNumber(string $documentNumber, int $customerId): CustomerRetailDocument
    {
        return CustomerRetailDocument::firstOrCreate(
            ['document_number' => $documentNumber],
            ['customer_id' => $customerId]
        );
    }

    public function save(CustomerRetailDocument $document): void
    {
        $document->save();
    }
}
