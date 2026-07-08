@extends('layouts.dashboard')

@section('title', 'Transações')
@section('breadcrumb', 'Transações')

@section('page-header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Transações</h1>
        <p class="mt-1 text-sm text-slate-500">Pagamentos Stripe e movimentos Wallester — visões separadas por origem.</p>
    </div>
</div>
@endsection

@section('content')

@php
    $tabQuery = fn (string $targetTab) => request()->fullUrlWithQuery(array_merge(
        request()->except(['page']),
        ['tab' => $targetTab],
    ));

    $partnerFundingLabels = [
        'not_required' => 'N/A',
        'pending' => 'Pendente',
        'awaiting_liquidity' => 'Aguarda liquidez',
        'failed' => 'Falhou',
        'completed' => 'Concluído',
    ];

    $movementTypeLabels = [
        'account_transfer' => 'Transferência',
        'card_purchase' => 'Compra cartão',
        'card_refund' => 'Reembolso cartão',
        'inbound_payment' => 'Entrada',
        'fee' => 'Taxa',
    ];

    $movementStatusLabels = [
        'pending' => 'Pendente',
        'completed' => 'Concluído',
        'failed' => 'Falhou',
        'canceled' => 'Cancelado',
    ];

    $movementSourceLabels = [
        'api' => 'API',
        'webhook' => 'Webhook',
        'sync' => 'Sync',
    ];
@endphp

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">

    {{-- Tabs --}}
    <div class="px-5 pt-4 border-b border-slate-100">
        <div class="flex gap-1">
            <a href="{{ $tabQuery('stripe') }}"
               class="px-4 py-2.5 text-sm font-semibold rounded-t-xl border-b-2 transition-colors
                      {{ $tab === 'stripe'
                          ? 'border-indigo-600 text-indigo-700 bg-indigo-50/60'
                          : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50' }}">
                Stripe
                @if ($tab === 'stripe' && $stripeTransactions)
                <span class="ml-1 text-xs font-medium text-slate-400">({{ number_format($stripeTransactions->total()) }})</span>
                @endif
            </a>
            <a href="{{ $tabQuery('wallester') }}"
               class="px-4 py-2.5 text-sm font-semibold rounded-t-xl border-b-2 transition-colors
                      {{ $tab === 'wallester'
                          ? 'border-violet-600 text-violet-700 bg-violet-50/60'
                          : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50' }}">
                Wallester
                @if ($tab === 'wallester' && $movements)
                <span class="ml-1 text-xs font-medium text-slate-400">({{ number_format($movements->total()) }})</span>
                @endif
            </a>
        </div>
    </div>

    @if ($tab === 'stripe')
    {{-- Stripe filters --}}
    <div class="px-5 py-4 border-b border-slate-100">
        <form method="GET" action="{{ route('dashboard.transactions') }}" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" value="stripe">

            <div class="relative flex-1 min-w-48">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="ID, cliente, PaymentIntent, Charge..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition">
            </div>

            <select name="type"
                    class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 text-slate-700 transition">
                <option value="">Tipo</option>
                @foreach ($transactionTypes as $type)
                    <option value="{{ $type->value }}" {{ request('type') === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                @endforeach
            </select>

            <select name="status"
                    class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 text-slate-700 transition">
                <option value="">Status Stripe</option>
                @foreach (['succeeded', 'pending', 'failed', 'canceled'] as $statusOption)
                    <option value="{{ $statusOption }}" {{ request('status') === $statusOption ? 'selected' : '' }}>{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>

            <select name="partner_funding_status"
                    class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 text-slate-700 transition">
                <option value="">Funding Wallester</option>
                @foreach ($partnerFundingStatuses as $fundingStatus)
                    <option value="{{ $fundingStatus->value }}" {{ request('partner_funding_status') === $fundingStatus->value ? 'selected' : '' }}>
                        {{ $partnerFundingLabels[$fundingStatus->value] ?? $fundingStatus->value }}
                    </option>
                @endforeach
            </select>

            <input type="date" name="from" value="{{ request('from') }}"
                   class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 text-slate-700 transition">
            <input type="date" name="to" value="{{ request('to') }}"
                   class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 text-slate-700 transition">

            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
                Filtrar
            </button>

            @if (request()->hasAny(['search', 'type', 'status', 'partner_funding_status', 'from', 'to']))
            <a href="{{ route('dashboard.transactions', ['tab' => 'stripe']) }}"
               class="px-3 py-2 text-sm text-slate-500 hover:text-slate-700 transition">Limpar</a>
            @endif
        </form>
    </div>

    {{-- Stripe table --}}
    <div class="overflow-x-auto">
        <table class="data-table w-full">
            <thead class="text-left">
                <tr>
                    <th class="text-left">Data</th>
                    <th class="text-left">Cliente</th>
                    <th class="text-left">Tipo</th>
                    <th class="text-left">Montante</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Rota</th>
                    <th class="text-left">Liquidação</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stripeTransactions as $tx)
                @php
                    $isCredit = $tx->direction?->value === 'credit' || $tx->direction === 'credit';
                    $txDate = $tx->stripe_created_at ?? $tx->created_at;
                    $settlementRoute = $tx->topupSettlementRoute();
                @endphp
                <tr>
                    <td class="whitespace-nowrap">
                        <span class="text-slate-900 font-medium">{{ $txDate?->format('d/m/Y') }}</span>
                        <span class="text-slate-400 text-xs block">{{ $txDate?->format('H:i') }}</span>
                    </td>
                    <td>
                        @if ($tx->customer)
                        <a href="{{ route('dashboard.customers.show', $tx->customer) }}"
                           class="text-sm font-medium text-indigo-600 hover:underline truncate block max-w-[180px]">
                            {{ $tx->customer->name }}
                        </a>
                        <span class="text-xs text-slate-400 truncate block max-w-[180px]">{{ $tx->customer->email }}</span>
                        @else
                        <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="text-sm text-slate-700">{{ $tx->type?->label() ?? '—' }}</span>
                        <span class="text-xs text-slate-400 block">#{{ $tx->id }}</span>
                    </td>
                    <td class="whitespace-nowrap">
                        <span class="font-semibold {{ $isCredit ? 'text-emerald-600' : 'text-red-500' }}">
                            {{ $isCredit ? '+' : '−' }}{{ number_format(abs($tx->amount) / 100, 2, ',', '.') }} {{ strtoupper($tx->currency) }}
                        </span>
                        @if ($tx->net_amount && $tx->net_amount !== $tx->amount)
                        <span class="text-xs text-slate-400 block">Líq. {{ number_format($tx->net_amount / 100, 2, ',', '.') }}</span>
                        @endif
                    </td>
                    <td>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border {{ match (strtolower($tx->status ?? '')) {
                            'succeeded', 'paid', 'complete', 'completed', 'settled' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'pending', 'processing', 'requires_capture' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'failed', 'canceled', 'cancelled', 'refunded', 'disputed' => 'bg-red-50 text-red-700 border-red-200',
                            default => 'bg-slate-100 text-slate-600 border-slate-200',
                        } }}">
                            {{ $tx->status ?? '—' }}
                        </span>
                    </td>
                    <td>
                        @if ($settlementRoute)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border {{ $settlementRoute->badgeClasses($tx) }}">
                            {{ $settlementRoute->label() }}
                        </span>
                        @if ($detail = $settlementRoute->detail($tx))
                        <span class="text-[10px] text-slate-400 block mt-0.5">{{ $detail }}</span>
                        @endif
                        @else
                        <span class="text-slate-400 text-xs">—</span>
                        @endif
                    </td>
                    <td class="text-xs text-slate-500">
                        @if ($settlementRoute?->showAvailableOn($tx))
                            @php $available = $tx->available_on->isPast() || $tx->available_on->isToday() || $tx->balance_settled_at; @endphp
                            <span class="{{ $available ? 'text-slate-500' : 'text-amber-600' }}">
                                {{ $available ? 'Desde' : 'Em' }} {{ $tx->available_on->format('d/m/Y') }}
                            </span>
                        @elseif ($settlementRoute === \App\Domains\Wallet\Enums\TopupSettlementRoute::WallesterImmediate)
                            <span class="{{ $tx->partner_funding_status === \App\Domains\Wallet\Enums\PartnerFundingStatus::Completed ? 'text-emerald-600' : 'text-amber-600' }}">
                                {{ $tx->partner_funding_status === \App\Domains\Wallet\Enums\PartnerFundingStatus::Completed ? 'Saldo libertado' : 'Saldo em pending' }}
                            </span>
                        @elseif ($tx->balance_settled_at)
                            <span class="text-emerald-600">Liquidado</span>
                        @else
                            <span class="text-slate-300">—</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @can('customers.view')
                        @if ($tx->customer)
                        <a href="{{ route('dashboard.customers.show', $tx->customer) }}#transactions"
                           class="text-xs text-indigo-600 hover:underline">Ver cliente</a>
                        @endif
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="py-12 text-center text-sm text-slate-400">
                        Nenhuma transação Stripe encontrada.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($stripeTransactions->hasPages())
    <div class="px-5 py-4 border-t border-slate-100">
        {{ $stripeTransactions->links() }}
    </div>
    @endif

    @else
    {{-- Wallester filters --}}
    <div class="px-5 py-4 border-b border-slate-100">
        <form method="GET" action="{{ route('dashboard.transactions') }}" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" value="wallester">

            <div class="relative flex-1 min-w-48">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="ID, cliente, conta, idempotency..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-violet-300 focus:border-violet-400 transition">
            </div>

            <select name="movement_type"
                    class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-violet-300 text-slate-700 transition">
                <option value="">Tipo</option>
                @foreach ($movementTypes as $movementType)
                    <option value="{{ $movementType->value }}" {{ request('movement_type') === $movementType->value ? 'selected' : '' }}>
                        {{ $movementTypeLabels[$movementType->value] ?? $movementType->value }}
                    </option>
                @endforeach
            </select>

            <select name="movement_status"
                    class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-violet-300 text-slate-700 transition">
                <option value="">Status</option>
                @foreach ($movementStatuses as $movementStatus)
                    <option value="{{ $movementStatus->value }}" {{ request('movement_status') === $movementStatus->value ? 'selected' : '' }}>
                        {{ $movementStatusLabels[$movementStatus->value] ?? $movementStatus->value }}
                    </option>
                @endforeach
            </select>

            <select name="direction"
                    class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-violet-300 text-slate-700 transition">
                <option value="">Direção</option>
                @foreach ($movementDirections as $movementDirection)
                    <option value="{{ $movementDirection->value }}" {{ request('direction') === $movementDirection->value ? 'selected' : '' }}>
                        {{ strtoupper($movementDirection->value) }}
                    </option>
                @endforeach
            </select>

            <select name="source"
                    class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-violet-300 text-slate-700 transition">
                <option value="">Origem</option>
                @foreach ($movementSources as $movementSource)
                    <option value="{{ $movementSource->value }}" {{ request('source') === $movementSource->value ? 'selected' : '' }}>
                        {{ $movementSourceLabels[$movementSource->value] ?? $movementSource->value }}
                    </option>
                @endforeach
            </select>

            <input type="date" name="from" value="{{ request('from') }}"
                   class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-violet-300 text-slate-700 transition">
            <input type="date" name="to" value="{{ request('to') }}"
                   class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-violet-300 text-slate-700 transition">

            <button type="submit" class="px-4 py-2 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition shadow-sm shadow-violet-500/25">
                Filtrar
            </button>

            @if (request()->hasAny(['search', 'movement_type', 'movement_status', 'direction', 'source', 'from', 'to']))
            <a href="{{ route('dashboard.transactions', ['tab' => 'wallester']) }}"
               class="px-3 py-2 text-sm text-slate-500 hover:text-slate-700 transition">Limpar</a>
            @endif
        </form>
    </div>

    {{-- Wallester table --}}
    <div class="overflow-x-auto">
        <table class="data-table w-full">
            <thead class="text-left">
                <tr>
                    <th class="text-left">Data</th>
                    <th class="text-left">Cliente / Conta</th>
                    <th class="text-left">Tipo</th>
                    <th class="text-left">Direção</th>
                    <th class="text-left">Montante</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Origem</th>
                    <th class="text-left">Referência</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                @php
                    $account = $movement->fundingAccount;
                    $customer = $account instanceof \App\Models\CustomerWallesterAccount ? $account->customer : null;
                    $direction = $movement->direction?->value;
                    $isIn = $direction === 'in';
                @endphp
                <tr>
                    <td class="whitespace-nowrap">
                        <span class="text-slate-900 font-medium">{{ $movement->created_at?->format('d/m/Y') }}</span>
                        <span class="text-slate-400 text-xs block">{{ $movement->created_at?->format('H:i') }}</span>
                    </td>
                    <td>
                        @if ($customer)
                        <a href="{{ route('dashboard.customers.show', $customer) }}"
                           class="text-sm font-medium text-violet-600 hover:underline truncate block max-w-[180px]">
                            {{ $customer->name }}
                        </a>
                        @endif
                        <span class="text-xs text-slate-400 font-mono truncate block max-w-[200px]">{{ $movement->external_account_id }}</span>
                    </td>
                    <td>
                        <span class="text-sm text-slate-700">{{ $movementTypeLabels[$movement->type?->value ?? ''] ?? ($movement->type?->value ?? '—') }}</span>
                        <span class="text-xs text-slate-400 block">#{{ $movement->id }}</span>
                    </td>
                    <td>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold {{ $isIn ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">
                            {{ strtoupper($direction ?? '—') }}
                        </span>
                    </td>
                    <td class="whitespace-nowrap">
                        <span class="font-semibold {{ $isIn ? 'text-emerald-600' : 'text-red-500' }}">
                            {{ $isIn ? '+' : '−' }}{{ number_format($movement->amount / 100, 2, ',', '.') }} {{ strtoupper($movement->currency) }}
                        </span>
                    </td>
                    <td>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border {{ match ($movement->status?->value) {
                            'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'failed', 'canceled' => 'bg-red-50 text-red-700 border-red-200',
                            default => 'bg-slate-100 text-slate-600 border-slate-200',
                        } }}">
                            {{ $movementStatusLabels[$movement->status?->value ?? ''] ?? ($movement->status?->value ?? '—') }}
                        </span>
                        @if ($movement->failure_reason)
                        <span class="text-[10px] text-red-500 block mt-0.5 truncate max-w-[140px]" title="{{ $movement->failure_reason }}">{{ Str::limit($movement->failure_reason, 40) }}</span>
                        @endif
                    </td>
                    <td class="text-xs text-slate-500">
                        {{ $movementSourceLabels[$movement->source?->value ?? ''] ?? ($movement->source?->value ?? '—') }}
                    </td>
                    <td class="text-xs">
                        @if ($movement->reference instanceof \App\Models\StripeTransaction)
                        <a href="{{ route('dashboard.transactions', ['tab' => 'stripe', 'search' => $movement->reference->id]) }}"
                           class="text-violet-600 hover:underline">
                            Stripe #{{ $movement->reference->id }}
                        </a>
                        @elseif ($movement->partner_external_id)
                        <span class="text-slate-500 font-mono truncate block max-w-[120px]" title="{{ $movement->partner_external_id }}">{{ Str::limit($movement->partner_external_id, 16) }}</span>
                        @else
                        <span class="text-slate-300">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="py-12 text-center text-sm text-slate-400">
                        Nenhum movimento Wallester encontrado.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($movements->hasPages())
    <div class="px-5 py-4 border-t border-slate-100">
        {{ $movements->links() }}
    </div>
    @endif
    @endif

</div>

@endsection
