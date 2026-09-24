<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\DTOs\NewsData;
use App\Domains\Backoffice\Services\NewsImageService;
use App\Models\News;

class UpdateNewsAction
{
    public function __construct(
        protected NewsImageService $images,
    ) {}

    public function execute(News $news, NewsData $data): News
    {
        $attributes = $data->toAttributes();
        unset($attributes['created_by']);

        $news->update($attributes);

        if ($data->image) {
            $news->update([
                'image_path' => $this->images->store($news->fresh() ?? $news, $data->image),
            ]);
        } elseif ($data->removeImage) {
            $this->images->deleteFile($news);
            $news->update(['image_path' => null]);
        }

        return $news->fresh(['author']) ?? $news;
    }

    public function checkNewsExpiration(News $news): void
    {
        if ($news->expiration_date && $news->expiration_date < now()) {
            $news->update(['status' => 'expired']);
        }
    }
}
