<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\CustomerResource;
use App\Models\CustomerDiditSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource['token'],
            'user' => CustomerResource::make($this->resource['user']),
            'didit_session' => !is_null($this->resource['didit_session']) ? DiditSessionResource::make($this->resource['didit_session']) : null,
            'requires_responsible' => $this->resource['requires_responsible'] ?? false,
            'kyc_status' => $this->resource['kyc_status'] ?? false,
            'email_verified' => $this->resource['email_verified'] ?? false,
            'document_verified' => $this->resource['document_verified'] ?? false,
            'message' => $this->resource['message'] ?? null,
        ];
    }
}
