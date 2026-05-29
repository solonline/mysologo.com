<?php

namespace App\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'currency'  => $this->resource['currency'],
            'available' => $this->resource['available'],
            'pending'   => $this->resource['pending'],
        ];
    }
}
