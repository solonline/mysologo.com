@extends('layouts.dashboard')

@section('title', 'Notícias')
@section('breadcrumb', 'Notícias')

@section('page-header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Notícias</h1>
        <p class="mt-1 text-sm text-slate-500">Gerir comunicados visíveis no app.</p>
    </div>
    @can('news.create')
    <a href="{{ route('dashboard.news.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nova notícia
    </a>
    @endcan
</div>
@endsection

@section('content')

<form method="GET" class="mb-4 flex flex-wrap gap-3">
    <input type="text" name="search" value="{{ request('search') }}"
           placeholder="Pesquisar título..."
           class="flex-1 min-w-[160px] max-w-xs px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
    <select name="status"
            class="px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
        <option value="">Todos os estados</option>
        <option value="draft" @selected(request('status') === 'draft')>Rascunho</option>
        <option value="published" @selected(request('status') === 'published')>Publicada</option>
    </select>
    <select name="audience"
            class="px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
        <option value="">Todos os públicos</option>
        <option value="all" @selected(request('audience') === 'all')>Todos</option>
        <option value="retail" @selected(request('audience') === 'retail')>Retail</option>
        <option value="corporate" @selected(request('audience') === 'corporate')>Corporate</option>
    </select>
    <button type="submit"
            class="px-4 py-2 bg-slate-800 text-white text-sm font-medium rounded-xl hover:bg-slate-700 transition">
        Filtrar
    </button>
    @if(request('search') || request('status') || request('audience'))
        <a href="{{ route('dashboard.news') }}"
           class="px-4 py-2 text-sm text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition">
            Limpar
        </a>
    @endif
</form>

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider">Notícia</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider hidden sm:table-cell">Público</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider">Estado</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider hidden md:table-cell">Publicação</th>
                <th class="px-5 py-3.5"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($items as $item)
            <tr class="hover:bg-slate-50/50 group transition-colors">
                <td class="px-5 py-4">
                    <div class="flex items-center gap-3 min-w-0">
                        @if ($item->image_path)
                            <img src="{{ $item->imageUrl() }}" alt=""
                                 class="w-12 h-12 rounded-xl object-cover border border-slate-100 flex-shrink-0">
                        @else
                            <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center text-slate-300 flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <p class="font-medium text-slate-800 truncate">
                                {{ $item->title }}
                                @if ($item->pinned)
                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-amber-50 text-amber-700 border border-amber-200">Fixa</span>
                                @endif
                            </p>
                            <p class="text-xs text-slate-400 mt-0.5 truncate">{{ $item->resolvedExcerpt() }}</p>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-4 text-slate-600 hidden sm:table-cell">
                    @if ($item->audience === 'retail')
                        Retail
                    @elseif ($item->audience === 'corporate')
                        Corporate
                    @else
                        Todos
                    @endif
                </td>
                <td class="px-5 py-4">
                    @if ($item->status === 'published')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">Publicada</span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500 border border-slate-200">Rascunho</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-slate-500 hidden md:table-cell text-xs">
                    {{ $item->published_at?->format('d/m/Y H:i') ?? '—' }}
                </td>
                <td class="px-5 py-4 text-right">
                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        @can('news.edit')
                        <a href="{{ route('dashboard.news.edit', $item) }}"
                           class="px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">Editar</a>
                        @endcan
                        @can('news.delete')
                        <form method="POST" action="{{ route('dashboard.news.destroy', $item) }}"
                              onsubmit="return confirm('Remover a notícia \'{{ addslashes($item->title) }}\'?')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition">
                                Remover
                            </button>
                        </form>
                        @endcan
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-5 py-12 text-center text-slate-400 text-sm">
                    Nenhuma notícia criada.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if ($items->hasPages())
        <div class="px-5 py-4 border-t border-slate-100">
            {{ $items->links() }}
        </div>
    @endif
</div>

@endsection
