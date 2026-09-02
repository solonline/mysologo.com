<?php

namespace App\Domains\Backoffice\Services;

use App\Models\News;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class NewsImageService
{
    public function store(News $news, UploadedFile $file): string
    {
        $this->deleteFile($news);

        return $file->store('news/'.$news->id, 'public');
    }

    public function deleteFile(News $news): void
    {
        if (! $news->image_path) {
            return;
        }

        Storage::disk('public')->delete($news->image_path);
    }

    public function deleteDirectory(News $news): void
    {
        Storage::disk('public')->deleteDirectory('news/'.$news->id);
    }
}
