@extends('layouts.dashboard')

@section('title', 'Receita Platform')
@section('breadcrumb', 'Receita Platform')

@section('page-header')
<div class="flex items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Receita Platform</h1>
        <p class="mt-1 text-sm text-slate-500">Taxas Yoop cobradas em top-ups e SecurePay. Separadas entre liquidadas e a aguardar settlement Stripe.</p>
    </div>
    <a href="{{ route('dashboard.transactions', ['tab' => 'stripe', 'with_platform_fee' => 1]) }}"
       class="btn-secondary btn-sm">
        Ver em Transações
    </a>
</div>
@endsection

@section('content')

@if (! $overview->ledgerMatchesStripe)
<div class="mb-6 flex items-start gap-4 px-5 py-4 rounded-2xl" style="background:#fffbeb;border:1px solid #fcd34d;">
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-amber-800">Divergência entre Stripe e ledger</p>
        <p class="text-xs text-amber-700 mt-0.5">O acumulado de <code class="text-[11px]">platform_fee</code> não coincide com os créditos no ledger da conta platform. Execute <code class="text-[11px]">php artisan wallet:audit</code> ou <code class="text-[11px]">wallet:platform-revenue</code>.</p>
    </div>
</div>
@endif

<div class="mb-6">
    <form method="GET" action="{{ route('dashboard.platform-revenue') }}" class="flex flex-wrap items-center gap-3">
        <input type="date" name="from" value="{{ request('from') }}"
               class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-emerald-300 text-slate-700 transition">
        <input type="date" name="to" value="{{ request('to') }}"
               class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-emerald-300 text-slate-700 transition">
               <div class="flex items-center gap-3">
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-gray text-sm font-semibold rounded-lg shadow-md hover:bg-emerald-700 hover:shadow-lg transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2l-7 7v6l-4-2v-4L3 6V4z" />
                    </svg>
                    Filtrar período
                </button>

                @if (request()->hasAny(['from', 'to']))
                    <a
                        href="{{ route('dashboard.platform-revenue') }}"
                        class="inline-flex items-center gap-2 px-5 py-2.5 text-red border border-slate-300 bg-white text-slate-700 text-sm font-semibold rounded-lg shadow-sm hover:bg-slate-100 hover:border-slate-400 transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Limpar filtros
                    </a>
                @endif
            </div>
    </form>
</div>

@forelse ($overview->currencies as $summary)
<div class="mb-8">
    <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-4">{{ $summary->currency }}</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-4">
        <div class="card p-5">
            <p class="text-xs font-semibold text-slate-500 uppercase">Total acumulado</p>
            <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($summary->accruedCents / 100, 2, ',', '.') }} {{ $summary->currency }}</p>
            <p class="text-xs text-slate-400 mt-2">{{ number_format($summary->transactionCount) }} transações · este mês {{ number_format($summary->monthAccruedCents / 100, 2, ',', '.') }}</p>
        </div>
        <div class="card p-5 border-emerald-200/80">
            <p class="text-xs font-semibold text-emerald-700 uppercase">Liquidado</p>
            <p class="text-2xl font-bold text-emerald-800 mt-1">{{ number_format($summary->settledCents / 100, 2, ',', '.') }} {{ $summary->currency }}</p>
            <p class="text-xs text-emerald-700/70 mt-2">{{ number_format($summary->settledTransactionCount) }} txs · este mês {{ number_format($summary->monthSettledCents / 100, 2, ',', '.') }}</p>
        </div>
        <div class="card p-5 border-amber-200/80">
            <p class="text-xs font-semibold text-amber-700 uppercase">Aguarda liquidação</p>
            <p class="text-2xl font-bold text-amber-800 mt-1">{{ number_format($summary->pendingCents / 100, 2, ',', '.') }} {{ $summary->currency }}</p>
            <p class="text-xs text-amber-700/70 mt-2">{{ number_format($summary->pendingTransactionCount) }} txs · este mês {{ number_format($summary->monthPendingCents / 100, 2, ',', '.') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="card p-5">
            <p class="text-xs font-semibold text-slate-500 uppercase">No ledger platform</p>
            <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($summary->ledgerCents / 100, 2, ',', '.') }} {{ $summary->currency }}</p>
            <p class="text-xs text-slate-400 mt-2">Créditos já registados na conta platform</p>
            @if (! $summary->ledgerMatchesStripe)
            <p class="text-xs text-amber-600 mt-2 font-medium">Delta vs acumulado: {{ number_format(($summary->accruedCents - $summary->ledgerCents) / 100, 2, ',', '.') }} {{ $summary->currency }}</p>
            @endif
        </div>
        <div class="card p-5 bg-slate-50/60">
            <p class="text-xs font-semibold text-slate-500 uppercase mb-2">Como ler</p>
            <ul class="text-xs text-slate-600 space-y-1.5 leading-relaxed">
                <li><span class="font-semibold text-emerald-700">Liquidado</span> — taxa de transações com settlement concluído (<code class="text-[10px]">balance_settled_at</code>).</li>
                <li><span class="font-semibold text-amber-700">Aguarda liquidação</span> — taxa já calculada, à espera do payout Stripe / settlement.</li>
                <li><span class="font-semibold text-slate-700">Ledger</span> — valor já creditado na wallet platform (pode incluir pendentes).</li>
            </ul>
        </div>
    </div>

    @if ($summary->monthlyBreakdown !== [])
    <div class="card p-5 mb-6">
        <p class="text-xs font-semibold text-slate-500 uppercase mb-3">Últimos meses</p>
        <div class="flex flex-wrap gap-3">
            @foreach ($summary->monthlyBreakdown as $monthRow)
            <div class="rounded-xl bg-slate-50 border border-slate-100 px-3 py-2 text-center min-w-[108px]">
                <p class="text-[10px] font-semibold text-slate-500">{{ $monthRow['month'] }}</p>
                <p class="text-sm font-bold text-slate-800">{{ number_format($monthRow['amount_cents'] / 100, 2, ',', '.') }}</p>
                <p class="text-[10px] text-emerald-700 mt-1">Liq. {{ number_format($monthRow['settled_cents'] / 100, 2, ',', '.') }}</p>
                <p class="text-[10px] text-amber-700">Pend. {{ number_format($monthRow['pending_cents'] / 100, 2, ',', '.') }}</p>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@empty
<div class="card p-8 text-center">
    <p class="text-sm text-slate-500">Ainda não há taxas platform registadas.</p>
</div>
@endforelse

<div class="card overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="text-sm font-bold text-slate-800">Transações com taxa platform</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table w-full">
            <thead class="text-left">
                <tr>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th>Bruto</th>
                    <th>Taxa Stripe</th>
                    <th>Taxa Platform</th>
                    <th>Líquido</th>
                    <th>Liquidação</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $tx)
                @php
                    $txDate = $tx->stripe_created_at ?? $tx->created_at;
                    $isSettled = $tx->balance_settled_at !== null;
                @endphp
                <tr>
                    <td class="text-sm text-slate-600 whitespace-nowrap">{{ $txDate?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>
                        <p class="text-sm font-medium text-slate-800">{{ $tx->customer?->name ?? '—' }}</p>
                        <p class="text-xs text-slate-400">#{{ $tx->id }} · {{ $tx->type?->label() ?? '—' }}</p>
                    </td>
                    <td class="text-sm font-mono text-slate-700">{{ number_format($tx->amount / 100, 2, ',', '.') }} {{ strtoupper($tx->currency) }}</td>
                    <td class="text-sm font-mono text-slate-500">{{ number_format($tx->stripe_fee / 100, 2, ',', '.') }}</td>
                    <td class="text-sm font-mono font-semibold text-emerald-700">{{ number_format($tx->platform_fee / 100, 2, ',', '.') }}</td>
                    <td class="text-sm font-mono text-slate-700">{{ number_format($tx->net_amount / 100, 2, ',', '.') }}</td>
                    <td>
                        @if ($isSettled)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">Liquidado</span>
                        @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-100">Aguarda</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('dashboard.transactions', ['tab' => 'stripe', 'search' => $tx->id]) }}"
                           class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Ver →</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-sm text-slate-400 py-8">Nenhuma transação com taxa platform no período.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($transactions->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">
        {{ $transactions->links() }}
    </div>
    @endif
</div>

@endsection
