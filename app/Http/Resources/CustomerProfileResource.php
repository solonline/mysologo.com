<?php

namespace App\Http\Resources;

use App\Http\Resources\Auth\DiditSessionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customer = $this->resource['customer'];

        return [
            'id'       => $customer->id,
            'name'     => $customer->name,
            'username' => $customer->username,
            'type'     => $customer->type,
            'email'    => $customer->email,
            'language' => $customer->language,
            'currency' => $customer->currency,
            'avatar'   => $customer->avatar,
            'show_profile_pic' => (bool) $customer->show_profile_pic,
            'phone'    => $customer->phone,

            'status'            => $customer->status,
            'email_verified_at' => $customer->email_verified_at,
            'created_at'        => $customer->created_at,
            'updated_at'        => $customer->updated_at,

            'requires_responsible' => $this->resource['requires_responsible'],
            'kyc_status'           => $this->resource['kyc_status'],
            'email_verified'       => $this->resource['email_verified'],
            'document_verified'    => $this->resource['document_verified'],
            'phone_verified'       => $this->resource['phone_verified'],
            'didit_session'        => $this->resource['didit_session']
                ? DiditSessionResource::make($this->resource['didit_session'])
                : null,
            'message' => $this->resource['message'],
        ];
    }
}
