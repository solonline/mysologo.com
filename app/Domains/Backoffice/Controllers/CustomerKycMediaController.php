<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Kyc\Support\CustomerKycMedia;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerRetailDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CustomerKycMediaController extends Controller
{
    public function show(Customer $customer, CustomerRetailDocument $document, string $field): Response
    {
        abort_unless($document->customer_id === $customer->id, 404);
        abort_unless(in_array($field, CustomerKycMedia::FIELDS, true), 404);

        $path = $document->{$field};
        $prefix = "customer/{$customer->id}/didit/{$document->id}/{$field}.";

        abort_unless(is_string($path) && str_starts_with($path, $prefix), 404);
        abort_unless(! str_contains($path, '..'), 404);
        abort_unless(Storage::disk(CustomerKycMedia::DISK)->exists($path), 404);

        return Storage::disk(CustomerKycMedia::DISK)->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
