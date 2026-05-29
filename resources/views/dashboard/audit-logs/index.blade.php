@extends('layouts.dashboard')

@section('title', 'Auditoria')
@section('breadcrumb', 'Auditoria')

@section('page-header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Auditoria</h1>
        <p class="mt-1 text-sm text-slate-500">Registo de todas as alterações realizadas no sistema.</p>
    </div>
</div>
@endsection

@section('content')

@php
    $eventLabels = [
        'created' => ['label' => 'Criado',      'color' => 'emerald'],
        'updated' => ['label' => 'Atualizado',  'color' => 'amber'],
        'deleted' => ['label' => 'Removido',    'color' => 'red'],
    ];

    $logLabels = [
        'customers'    => 'Clientes',
        'transactions' => 'Transações',
        'default'      => 'Sistema',
    ];

    $subjectLabels = [
        'App\\Models\\Customer'    => 'Cliente',
        'App\\Models\\Transaction' => 'Transação',
    ];

    function eventBadge(string $event): string {
        return match($event) {
            'created' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'updated' => 'bg-amber-50 text-amber-700 border-amber-200',
            'deleted' => 'bg-red-50 text-red-700 border-red-200',
            default   => 'bg-slate-50 text-slate-600 border-slate-200',
        };
    }

    function eventDot(string $event): string {
        return match($event) {
            'created' => 'bg-emerald-500',
            'updated' => 'bg-amber-500',
            'deleted' => 'bg-red-500',
            default   => 'bg-slate-400',
        };
    }
@endphp

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">

    {{-- Filters --}}
    <div class="px-5 py-4 border-b border-slate-100">
        <form method="GET" action="{{ route('dashboard.audit-logs') }}" class="flex flex-wrap items-center gap-3">

            <div class="relative flex-1 min-w-48">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Buscar por nome do utilizador ou ID do registo..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition">
            </div>

            <select name="log"
                    class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 text-slate-700 transition">
                <option value="">Todos os canais</option>
                <option value="customers"    {{ request('log') === 'customers'    ? 'selected' : '' }}>Clientes</option>
                <option value="transactions" {{ request('log') === 'transactions' ? 'selected' : '' }}>Transações</option>
                <option value="default"      {{ request('log') === 'default'      ? 'selected' : '' }}>Sistema</option>
            </select>

            <select name="event"
                    class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 text-slate-700 transition">
                <option value="">Todos os eventos</option>
                <option value="created" {{ request('event') === 'created' ? 'selected' : '' }}>Criado</option>
                <option value="updated" {{ request('event') === 'updated' ? 'selected' : '' }}>Atualizado</option>
                <option value="deleted" {{ request('event') === 'deleted' ? 'selected' : '' }}>Removido</option>
            </select>

            <input type="date" name="from" value="{{ request('from') }}"
                   class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 text-slate-700 transition">

            <input type="date" name="to" value="{{ request('to') }}"
                   class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 text-slate-700 transition">

            <button type="submit"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
                Filtrar
            </button>

            @if (request()->hasAny(['search', 'log', 'event', 'from', 'to']))
            <a href="{{ route('dashboard.audit-logs') }}"
               class="px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition">
                Limpar
            </a>
            @endif
        </form>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-100">
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-5 py-3">Evento</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Entidade</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 hidden md:table-cell">Alterado por</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 hidden lg:table-cell">Canal</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 hidden sm:table-cell">Data/Hora</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                @php
                    $event      = $log->event ?? $log->description ?? 'updated';
                    $subjectKey = $log->subject_type ?? '';
                    $subjectLabel = $subjectLabels[$subjectKey] ?? class_basename($subjectKey ?: 'Registo');
                    $logLabel   = $logLabels[$log->log_name] ?? ucfirst($log->log_name ?? 'sistema');
                    $causerName = $log->causer?->name ?? 'Sistema';
                    $causerInitials = strtoupper(substr($causerName, 0, 2));
                    $changedCount = count((array) ($log->properties['attributes'] ?? []));
                @endphp
                <tr class="hover:bg-slate-50/50 transition-colors group">

                    {{-- Evento --}}
                    <td class="px-5 py-3.5">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border {{ eventBadge($event) }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ eventDot($event) }}"></span>
                            {{ $eventLabels[$event]['label'] ?? ucfirst($event) }}
                        </span>
                    </td>

                    {{-- Entidade --}}
                    <td class="px-4 py-3.5">
                        <div class="flex items-center gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">
                                    {{ $subjectLabel }}
                                    @if ($log->subject_id)
                                        <span class="text-slate-400 font-normal">#{{ $log->subject_id }}</span>
                                    @endif
                                </p>
                                @if ($event === 'updated' && $changedCount > 0)
                                    <p class="text-xs text-slate-400 mt-0.5">
                                        {{ $changedCount }} {{ $changedCount === 1 ? 'campo alterado' : 'campos alterados' }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </td>

                    {{-- Alterado por --}}
                    <td class="px-4 py-3.5 hidden md:table-cell">
                        <div class="flex items-center gap-2">
                            @if ($log->causer)
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0"
                                     style="background: linear-gradient(135deg,#7c3aed,#a855f7);">
                                    {{ $causerInitials }}
                                </div>
                                <span class="text-sm text-slate-700">{{ $causerName }}</span>
                            @else
                                <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <span class="text-sm text-slate-400 italic">Sistema</span>
                            @endif
                        </div>
                    </td>

                    {{-- Canal --}}
                    <td class="px-4 py-3.5 hidden lg:table-cell">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
                            {{ $logLabel }}
                        </span>
                    </td>

                    {{-- Data/Hora --}}
                    <td class="px-4 py-3.5 hidden sm:table-cell">
                        <div>
                            <p class="text-sm text-slate-700">{{ $log->created_at?->format('d/m/Y') }}</p>
                            <p class="text-xs text-slate-400">{{ $log->created_at?->format('H:i:s') }}</p>
                        </div>
                    </td>

                    {{-- Ações --}}
                    <td class="px-4 py-3.5 text-right">
                        <div class="flex items-center justify-end opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="{{ route('dashboard.audit-logs.show', $log) }}"
                               class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 border border-slate-200 rounded-lg transition-colors">
                                Ver detalhes
                            </a>
                        </div>
                    </td>

                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center">
                                <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-700">Nenhum registo encontrado</p>
                                <p class="text-xs text-slate-400 mt-1">Tente ajustar os filtros para ver mais resultados.</p>
                            </div>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($logs->hasPages())
    <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
        <p class="text-xs text-slate-500">
            Mostrando <span class="font-semibold text-slate-700">{{ $logs->firstItem() }}</span>–<span class="font-semibold text-slate-700">{{ $logs->lastItem() }}</span>
            de <span class="font-semibold text-slate-700">{{ $logs->total() }}</span> registos
        </p>
        <div class="flex items-center gap-1">
            @if ($logs->onFirstPage())
                <span class="px-3 py-1.5 text-xs text-slate-300 bg-slate-50 border border-slate-200 rounded-lg cursor-not-allowed">← Anterior</span>
            @else
                <a href="{{ $logs->previousPageUrl() }}"
                   class="px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">← Anterior</a>
            @endif
            @foreach ($logs->getUrlRange(max(1, $logs->currentPage() - 2), min($logs->lastPage(), $logs->currentPage() + 2)) as $page => $url)
                @if ($page === $logs->currentPage())
                    <span class="px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 border border-indigo-600 rounded-lg">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">{{ $page }}</a>
                @endif
            @endforeach
            @if ($logs->hasMorePages())
                <a href="{{ $logs->nextPageUrl() }}"
                   class="px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">Próximo →</a>
            @else
                <span class="px-3 py-1.5 text-xs text-slate-300 bg-slate-50 border border-slate-200 rounded-lg cursor-not-allowed">Próximo →</span>
            @endif
        </div>
    </div>
    @endif

</div>

@endsection
