<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\Services\NewsImageService;
use App\Models\News;

class DeleteNewsAction
{
    public function __construct(
        protected NewsImageService $images,
    ) {}

    public function execute(News $news): void
    {
        $this->images->deleteDirectory($news);
        $news->delete();
    }
}
