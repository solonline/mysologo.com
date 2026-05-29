<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\CustomerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TpaAuthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'token'   => $this->resource['token'],
            'context' => $this->resource['context'],
            'user'    => CustomerResource::make($this->resource['user']),
            'tpa'     => [
                'id'     => $this->resource['tpa']->id,
                'serial' => $this->resource['tpa']->serial,
                'status' => $this->resource['tpa']->status,
            ],
        ];
    }
}
