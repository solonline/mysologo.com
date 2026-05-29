<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentUploadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'field' => $this->resource['field'],
            'url'   => $this->resource['url'],
        ];
    }
}
