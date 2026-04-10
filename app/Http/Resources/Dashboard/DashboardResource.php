<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'balance'      => $this->resource['balance'],
            'transactions' => DashboardTransactionResource::collection($this->resource['transactions']),
        ];
    }
}
