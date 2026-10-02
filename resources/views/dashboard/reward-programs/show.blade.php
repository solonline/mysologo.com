@extends('layouts.dashboard')

@section('title', $program->name)
@section('breadcrumb', 'Recompensas')

@section('page-header')
<div class="flex items-center justify-between gap-4">
    <div class="flex items-center gap-3 min-w-0">
        <a href="{{ route('dashboard.reward-programs') }}"
           class="p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-slate-900 truncate">{{ $program->name }}</h1>
            <p class="mt-0.5 text-sm text-slate-400 font-mono">{{ $program->slug }}</p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
        @can('rewards.edit')
        <a href="{{ route('dashboard.reward-programs.edit', $program) }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
            Editar
        </a>
        @endcan
        @can('rewards.delete')
            @if (! $program->isReferral())
            <form method="POST" action="{{ route('dashboard.reward-programs.destroy', $program) }}"
                  onsubmit="return confirm('Remover o programa \'{{ addslashes($program->name) }}\'?')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-xl hover:bg-red-100 transition">
                    Remover
                </button>
            </form>
            @endif
        @endcan
    </div>
</div>
@endsection

@section('content')

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Estado</p>
        <p class="mt-2">
            @if ($program->is_active)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">Activo</span>
            @else
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500 border border-slate-200">Inactivo</span>
            @endif
        </p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Validade</p>
        <p class="mt-2 text-sm text-slate-700">
            @if ($program->valid_from || $program->valid_until)
                {{ $program->valid_from?->format('d/m/Y H:i') ?? '…' }}
                —
                {{ $program->valid_until?->format('d/m/Y H:i') ?? '…' }}
            @else
                Sem prazo
            @endif
        </p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Rewards geradas</p>
        <p class="mt-2 text-lg font-semibold text-slate-800">{{ $program->rewards_count }}</p>
    </div>
</div>

@if ($program->description)
<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm px-5 py-4 mb-6">
    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Descrição / termos</p>
    <p class="text-sm text-slate-700 whitespace-pre-line">{{ $program->description }}</p>
</div>
@endif

<div class="flex items-center justify-between mb-3">
    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Rewards geradas</h2>
</div>

<form method="GET" class="mb-4 flex flex-wrap gap-3">
    <input type="text" name="search" value="{{ request('search') }}"
           placeholder="Cliente ou indicado..."
           class="flex-1 min-w-[160px] max-w-xs px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
    <select name="status"
            class="px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
        <option value="">Todos os estados</option>
        @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
        @endforeach
    </select>
    <button type="submit"
            class="px-4 py-2 bg-slate-800 text-white text-sm font-medium rounded-xl hover:bg-slate-700 transition">
        Filtrar
    </button>
    @if(request('search') || request('status'))
        <a href="{{ route('dashboard.reward-programs.show', $program) }}"
           class="px-4 py-2 text-sm text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition">
            Limpar
        </a>
    @endif
</form>

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider">Cliente</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider hidden md:table-cell">Indicado</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider">Estado</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider hidden sm:table-cell">Valor</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider hidden lg:table-cell">Criado</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($rewards as $reward)
            <tr class="hover:bg-slate-50/50 transition-colors">
                <td class="px-5 py-4">
                    @if ($reward->customer)
                        @can('customers.view')
                        <a href="{{ route('dashboard.customers.show', $reward->customer) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                            {{ $reward->customer->name }}
                        </a>
                        @else
                        <span class="font-medium text-slate-800">{{ $reward->customer->name }}</span>
                        @endcan
                        <p class="text-xs text-slate-400 mt-0.5">{{ $reward->customer->username ?? $reward->customer->email }}</p>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </td>
                <td class="px-5 py-4 hidden md:table-cell">
                    @if ($reward->referred)
                        @can('customers.view')
                        <a href="{{ route('dashboard.customers.show', $reward->referred) }}" class="text-slate-700 hover:text-indigo-700">
                            {{ $reward->referred->name }}
                        </a>
                        @else
                        <span class="text-slate-700">{{ $reward->referred->name }}</span>
                        @endcan
                        <p class="text-xs text-slate-400 mt-0.5">{{ $reward->referred->username }}</p>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </td>
                <td class="px-5 py-4">
                    @php
                        $status = $reward->status;
                        $badge = match ($status?->value) {
                            'eligible' => 'bg-green-50 text-green-700 border-green-200',
                            'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'paid' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                            'cancelled' => 'bg-slate-100 text-slate-500 border-slate-200',
                            default => 'bg-slate-100 text-slate-500 border-slate-200',
                        };
                    @endphp
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $badge }}">
                        {{ $status?->label() ?? $reward->status }}
                    </span>
                </td>
                <td class="px-5 py-4 text-slate-600 hidden sm:table-cell">
                    @if ($reward->amount_cents !== null)
                        {{ number_format($reward->amount_cents / 100, 2, ',', '.') }} {{ $reward->currency ?? '' }}
                    @else
                        —
                    @endif
                </td>
                <td class="px-5 py-4 text-slate-500 hidden lg:table-cell text-xs">
                    {{ $reward->created_at?->format('d/m/Y H:i') }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-5 py-12 text-center text-slate-400 text-sm">
                    Nenhuma reward gerada neste programa.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if ($rewards->hasPages())
        <div class="px-5 py-4 border-t border-slate-100">
            {{ $rewards->links() }}
        </div>
    @endif
</div>

@endsection
