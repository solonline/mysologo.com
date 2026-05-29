<?php

namespace App\Http\Resources;

use App\Http\Resources\Auth\DiditSessionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResponsibleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'responsible'   => CustomerResource::make($this->resource['responsible']),
            'didit_session' => $this->resource['didit_session']
                ? DiditSessionResource::make($this->resource['didit_session'])
                : null,
        ];
    }
}
