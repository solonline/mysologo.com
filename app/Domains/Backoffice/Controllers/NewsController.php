<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Actions\CreateNewsAction;
use App\Domains\Backoffice\Actions\DeleteNewsAction;
use App\Domains\Backoffice\Actions\UpdateNewsAction;
use App\Domains\Backoffice\DTOs\NewsData;
use App\Domains\Backoffice\Queries\ListNewsQuery;
use App\Domains\Backoffice\Requests\StoreNewsRequest;
use App\Domains\Backoffice\Requests\UpdateNewsRequest;
use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function __construct(
        protected ListNewsQuery $listNews,
        protected CreateNewsAction $createNews,
        protected UpdateNewsAction $updateNews,
        protected DeleteNewsAction $deleteNews,
    ) {}

    public function index(Request $request)
    {
        $result = $this->listNews->execute($request->only(['search', 'status', 'audience']));

        return view('dashboard.news.index', [
            'items' => $result->items->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('dashboard.news.create');
    }

    public function store(StoreNewsRequest $request)
    {
        $this->createNews->execute(
            NewsData::fromRequest($request, $request->user()?->id),
        );

        return redirect()->route('dashboard.news')
            ->with('success', 'Notícia criada com sucesso.');
    }

    public function edit(News $news)
    {
        return view('dashboard.news.edit', [
            'news' => $news->loadMissing('author'),
        ]);
    }

    public function update(UpdateNewsRequest $request, News $news)
    {
        $this->updateNews->execute(
            $news,
            NewsData::fromRequest($request),
        );

        return redirect()->route('dashboard.news')
            ->with('success', 'Notícia atualizada com sucesso.');
    }

    public function destroy(News $news)
    {
        $this->deleteNews->execute($news);

        return redirect()->route('dashboard.news')
            ->with('success', 'Notícia removida com sucesso.');
    }
}
