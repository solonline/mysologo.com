@extends('layouts.dashboard')

@section('title', 'Taxas Stripe')
@section('breadcrumb', 'Taxas Stripe')

@section('page-header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Taxas Stripe</h1>
        <p class="mt-1 text-sm text-slate-500">Configurar taxas por método de pagamento.</p>
    </div>
    @can('stripe_fees.create')
    <a href="{{ route('dashboard.stripe-fees.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nova Taxa
    </a>
    @endcan
</div>
@endsection

@section('content')

{{-- Filtro --}}
<form method="GET" class="mb-4 flex flex-wrap gap-3">
    <input type="text" name="search" value="{{ request('search') }}"
           placeholder="Pesquisar método..."
           class="flex-1 min-w-[160px] max-w-xs px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
    <select name="currency"
            class="px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
        <option value="">Todas as moedas</option>
        @foreach ($currencies as $currency)
            <option value="{{ $currency }}" @selected(request('currency') === $currency)>
                {{ strtoupper($currency) }}
            </option>
        @endforeach
    </select>
    <button type="submit"
            class="px-4 py-2 bg-slate-800 text-white text-sm font-medium rounded-xl hover:bg-slate-700 transition">
        Filtrar
    </button>
    @if(request('search') || request('currency'))
        <a href="{{ route('dashboard.stripe-fees') }}"
           class="px-4 py-2 text-sm text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition">
            Limpar
        </a>
    @endif
</form>

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider">Método</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider hidden md:table-cell">Taxa Stripe</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider hidden md:table-cell">Taxa Plataforma</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider hidden md:table-cell">Taxa Total</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider hidden sm:table-cell">Moeda</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider">Estado</th>
                <th class="px-5 py-3.5"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($fees as $fee)
            <tr class="hover:bg-slate-50/50 group transition-colors">
                <td class="px-5 py-4">
                    <div>
                        <p class="font-medium text-slate-800">{{ $fee->label }}</p>
                        <p class="text-xs text-slate-400 mt-0.5 font-mono">{{ $fee->payment_method }}</p>
                    </div>
                </td>
                <td class="px-5 py-4 text-slate-600 hidden md:table-cell">
                    <span>{{ number_format($fee->stripe_fee_percentage * 100, 2) }}%</span>
                    @if ($fee->stripe_fee_fixed > 0)
                        <span class="text-slate-400"> + {{ number_format($fee->stripe_fee_fixed, 2) }}</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-slate-600 hidden md:table-cell">
                    <span>{{ number_format($fee->platform_fee_percentage * 100, 2) }}%</span>
                    @if ($fee->platform_fee_fixed > 0)
                        <span class="text-slate-400"> + {{ number_format($fee->platform_fee_fixed, 2) }}</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-slate-800 font-medium hidden md:table-cell">
                    @php
                        $total_percentage = $fee->stripe_fee_percentage + $fee->platform_fee_percentage;
                        $total_fixed = $fee->stripe_fee_fixed + $fee->platform_fee_fixed;
                    @endphp
                    <span>{{ number_format($total_percentage * 100, 2) }}%</span>
                    @if ($total_fixed > 0)
                        <span class="text-slate-400"> + {{ number_format($total_fixed, 2) }}</span>
                    @endif
                <td class="px-5 py-4 text-slate-500 hidden sm:table-cell font-mono text-xs">
                    {{ $fee->currency }}
                </td>
                <td class="px-5 py-4">
                    @if ($fee->is_active)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">Ativo</span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500 border border-slate-200">Inativo</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-right">
                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        @can('stripe_fees.edit')
                        <a href="{{ route('dashboard.stripe-fees.edit', $fee) }}"
                           class="px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">Editar</a>
                        @endcan
                        @can('stripe_fees.delete')
                        <form method="POST" action="{{ route('dashboard.stripe-fees.destroy', $fee) }}"
                              onsubmit="return confirm('Remover a taxa \'{{ $fee->label }}\'?')">
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
                <td colspan="6" class="px-5 py-12 text-center text-slate-400 text-sm">
                    Nenhuma taxa configurada.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if ($fees->hasPages())
        <div class="px-5 py-4 border-t border-slate-100">
            {{ $fees->links() }}
        </div>
    @endif
</div>

@endsection
