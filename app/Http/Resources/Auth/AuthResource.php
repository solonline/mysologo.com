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
            'token'             => $this->resource['token'],
            'user'              => CustomerResource::make($this->resource['user']),
            'didit_session'     => DiditSessionResource::make($this->resource['didit_session']),
        ];
    }
}
