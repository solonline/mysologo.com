<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiditSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Se houver erro, retorna apenas a mensagem customizada
        if (isset($this->resource['success']) && $this->resource['success'] === false) {
            return [
                'success' => false,
                'message' => $this->resource['message'] ?? 'Não foi possível criar a sessão Didit.',
                'detail' => $this->resource['detail'] ?? null,
                'key' => $this->resource['key'] ?? null,
            ];
        }
        // Caso sucesso, retorna os dados válidos
        return $this->resource;
    }
}
