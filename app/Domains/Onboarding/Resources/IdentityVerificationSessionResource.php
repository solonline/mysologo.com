<?php

namespace App\Domains\Onboarding\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sessão de verificação de identidade — formato agnóstico ao provedor KYC.
 */
class IdentityVerificationSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource === null) {
            return [];
        }

        $data = is_array($this->resource) ? $this->resource : (array) $this->resource;

        return [
            'id' => $data['id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'status' => $data['status'] ?? null,
            'external_session_id' => $data['session_id'] ?? null,
            'verification_url' => $data['session_url'] ?? null,
            'callback_url' => $data['callback'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'created_at' => $data['created_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
        ];
    }
}
