<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'type'                => $this->type,
            'direction'           => $this->direction,
            'amount'              => $this->amount,
            'net_amount'          => $this->net_amount,
            'currency'            => $this->currency,
            'status'              => $this->status,
            'payment_method_type' => $this->payment_method_type,
            'description'         => $this->description,
            'created_at'          => $this->created_at,
        ];
    }
}
