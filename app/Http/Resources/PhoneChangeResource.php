<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhoneChangeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource['status'] ?? null,
            'phone' => $this->resource['phone'] ?? null,
            'phone_verified' => (bool) ($this->resource['phone_verified'] ?? false),
            'message' => $this->resource['message'] ?? null,
        ];
    }
}
