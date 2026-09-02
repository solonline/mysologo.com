<?php

namespace App\Domains\Customer\Controllers;

use App\Http\Resources\NewsResource;
use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class NewsController extends Controller
{
    /**
     * List News
     *
     * Returns published news visible to the authenticated customer (pinned first).
     *
     * @tags News
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 15)));

        $items = News::query()
            ->visibleTo($request->user())
            ->orderByDesc('pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return NewsResource::collection($items);
    }

    /**
     * Show News
     *
     * Returns a single published news item if it is visible to the authenticated customer.
     *
     * @tags News
     */
    public function show(Request $request, News $news): NewsResource
    {
        $visible = News::query()
            ->visibleTo($request->user())
            ->whereKey($news->id)
            ->first();

        abort_if($visible === null, 404);

        return new NewsResource($visible);
    }
}
