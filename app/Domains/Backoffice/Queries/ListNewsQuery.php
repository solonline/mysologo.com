<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Backoffice\DTOs\NewsListResult;
use App\Models\News;

class ListNewsQuery
{
    /**
     * @param  array<string, string|null>  $filters
     */
    public function execute(array $filters): NewsListResult
    {
        $query = News::query()->with('author');

        if ($search = $filters['search'] ?? null) {
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($audience = $filters['audience'] ?? null) {
            $query->where('audience', $audience);
        }

        return new NewsListResult(
            items: $query
                ->orderByDesc('pinned')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(20),
        );
    }
}
