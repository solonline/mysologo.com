<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'slug'               => $this->slug,
            'mode'               => $this->mode,
            'webhook_url'        => $this->webhook_url,
            'allowed_currencies' => $this->allowed_currencies,
            'is_active'          => $this->is_active,
            'created_at'         => $this->created_at,
        ];
    }
}
