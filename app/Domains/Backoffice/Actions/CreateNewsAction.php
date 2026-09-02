<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\DTOs\NewsData;
use App\Domains\Backoffice\Services\NewsImageService;
use App\Models\News;

class CreateNewsAction
{
    public function __construct(
        protected NewsImageService $images,
    ) {}

    public function execute(NewsData $data): News
    {
        $news = News::query()->create($data->toAttributes());

        if ($data->image) {
            $news->update([
                'image_path' => $this->images->store($news, $data->image),
            ]);
        }

        return $news->fresh(['author']) ?? $news;
    }
}
