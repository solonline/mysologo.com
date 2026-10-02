@extends('layouts.dashboard')

@section('title', 'Recompensas')
@section('breadcrumb', 'Recompensas')

@section('page-header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Recompensas</h1>
        <p class="mt-1 text-sm text-slate-500">Programas de recompensa e os ganhos gerados em cada um.</p>
    </div>
    @can('rewards.create')
    <a href="{{ route('dashboard.reward-programs.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Novo programa
    </a>
    @endcan
</div>
@endsection

@section('content')

<form method="GET" class="mb-4 flex flex-wrap gap-3">
    <input type="text" name="search" value="{{ request('search') }}"
           placeholder="Pesquisar nome ou código..."
           class="flex-1 min-w-[160px] max-w-xs px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
    <select name="status"
            class="px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
        <option value="">Todos</option>
        <option value="active" @selected(request('status') === 'active')>Activos</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
    </select>
    <button type="submit"
            class="px-4 py-2 bg-slate-800 text-white text-sm font-medium rounded-xl hover:bg-slate-700 transition">
        Filtrar
    </button>
    @if(request('search') || request('status'))
        <a href="{{ route('dashboard.reward-programs') }}"
           class="px-4 py-2 text-sm text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition">
            Limpar
        </a>
    @endif
</form>

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider">Programa</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider hidden sm:table-cell">Validade</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider">Estado</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider hidden md:table-cell">Rewards</th>
                <th class="px-5 py-3.5"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($items as $item)
            <tr class="hover:bg-slate-50/50 group transition-colors">
                <td class="px-5 py-4">
                    <a href="{{ route('dashboard.reward-programs.show', $item) }}" class="block min-w-0">
                        <p class="font-medium text-slate-800 truncate">{{ $item->name }}</p>
                        <p class="text-xs text-slate-400 mt-0.5 font-mono">{{ $item->slug }}</p>
                    </a>
                </td>
                <td class="px-5 py-4 text-slate-500 hidden sm:table-cell text-xs">
                    @if ($item->valid_from || $item->valid_until)
                        {{ $item->valid_from?->format('d/m/Y') ?? '…' }}
                        —
                        {{ $item->valid_until?->format('d/m/Y') ?? '…' }}
                    @else
                        Sem prazo
                    @endif
                </td>
                <td class="px-5 py-4">
                    @if ($item->is_active)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">Activo</span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500 border border-slate-200">Inactivo</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-slate-600 hidden md:table-cell">
                    {{ $item->rewards_count }}
                </td>
                <td class="px-5 py-4 text-right">
                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <a href="{{ route('dashboard.reward-programs.show', $item) }}"
                           class="px-3 py-1.5 text-xs font-medium text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition">Ver</a>
                        @can('rewards.edit')
                        <a href="{{ route('dashboard.reward-programs.edit', $item) }}"
                           class="px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">Editar</a>
                        @endcan
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-5 py-12 text-center text-slate-400 text-sm">
                    Nenhum programa criado.
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
