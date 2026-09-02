<?php

namespace App\Http\Resources;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin News */
class NewsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->resolvedExcerpt(),
            'body' => $this->body,
            'image_url' => $this->imageUrl(),
            'audience' => $this->audience,
            'pinned' => (bool) $this->pinned,
            'published_at' => $this->published_at,
        ];
    }
}
