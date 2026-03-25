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
            return [
                'id' => $this->resource->id,
                'customer_id' => $this->resource->customer_id,
                'status' => $this->resource->status,
                'session_id' => $this->resource->session_id,
                'workflow_id' => $this->resource->workflow_id,
                'session_url' => $this->resource->session_url,
                'callback' => $this->resource->callback,
                'metadata' => $this->resource->metadata,
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ];
    }
}
