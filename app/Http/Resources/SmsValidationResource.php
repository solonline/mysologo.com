<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SmsValidationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        $payload = [
            'message' => $this->resource['message'] ?? null,
            'status' => $this->resource['status'] ?? 'success',
        ];

        if (array_key_exists('phone', $this->resource) && $this->resource['phone'] !== null) {
            $payload['phone'] = $this->resource['phone'];
        }

        return $payload;
    }
}
