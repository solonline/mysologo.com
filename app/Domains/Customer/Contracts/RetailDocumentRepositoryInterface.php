<?php

namespace App\Domains\Customer\Contracts;

use App\Models\CustomerRetailDocument;

interface RetailDocumentRepositoryInterface
{
    public function firstOrCreateByDocumentNumber(string $documentNumber, int $customerId): CustomerRetailDocument;

    public function save(CustomerRetailDocument $document): void;
}
