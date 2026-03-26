<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiditSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : (array) $this->resource;
        return [
            'id' => $data['id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'status' => $data['status'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'workflow_id' => $data['workflow_id'] ?? null,
            'session_url' => $data['session_url'] ?? null,
            'callback' => $data['callback'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'created_at' => $data['created_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
        ];
    }
}
