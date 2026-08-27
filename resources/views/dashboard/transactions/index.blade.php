@extends('layouts.dashboard')

@section('title', $customerContext ? 'Transações de '.$customerContext->name : 'Transações')
@section('breadcrumb', $customerContext ? 'Clientes / Transações' : 'Transações')

@section('page-header')
<div class="flex items-center gap-4">
    @if ($customerContext)
    <a href="{{ route('dashboard.customers.show', $customerContext) }}"
       class="p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors"
       aria-label="Voltar ao cliente">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    @endif
    <div>
        <h1 class="text-2xl font-bold text-slate-900">
            {{ $customerContext ? 'Transações de '.$customerContext->name : 'Transações' }}
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            {{ $customerContext ? $customerContext->email.' — pagamentos Stripe e movimentos Wallester.' : 'Pagamentos Stripe e movimentos Wallester — visões separadas por origem.' }}
        </p>
    </div>
</div>
@endsection

@section('content')

@php
    $transactionsRoute = $customerContext
        ? route('dashboard.customers.transactions', $customerContext)
        : route('dashboard.transactions');

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

<div x-data="{ showDetailsModal: false, details: null }" class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">

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
        <form method="GET" action="{{ $transactionsRoute }}" class="flex flex-wrap items-center gap-3">
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

            <label class="inline-flex items-center gap-2 py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 text-slate-700 cursor-pointer">
                <input type="checkbox" name="with_platform_fee" value="1" {{ request('with_platform_fee') ? 'checked' : '' }}
                       class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-300">
                Com taxa platform
            </label>

            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
                Filtrar
            </button>

            @if (request()->hasAny(['search', 'type', 'status', 'partner_funding_status', 'from', 'to', 'with_platform_fee']))
            <a href="{{ $transactionsRoute }}?tab=stripe"
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

                    $stripeFields = array_values(array_filter([
                        ['label' => 'Data', 'value' => $txDate?->format('d/m/Y H:i')],
                        $tx->customer ? ['label' => 'Cliente', 'value' => $tx->customer->name, 'link' => route('dashboard.customers.show', $tx->customer)] : null,
                        $tx->customer?->email ? ['label' => 'Email', 'value' => $tx->customer->email] : null,
                        ['label' => 'Tipo', 'value' => $tx->type?->label() ?? '—'],
                        ['label' => 'Direção', 'value' => $isCredit ? 'Crédito' : 'Débito'],
                        ['label' => 'Status', 'value' => $tx->status ?? '—'],
                        ['label' => 'Método pagamento', 'value' => $tx->payment_method_type ?? null],
                        $tx->stripe_payment_intent_id ? ['label' => 'PaymentIntent', 'value' => $tx->stripe_payment_intent_id, 'mono' => true, 'full' => true] : null,
                        $tx->stripe_charge_id ? ['label' => 'Charge', 'value' => $tx->stripe_charge_id, 'mono' => true, 'full' => true] : null,
                        $settlementRoute ? ['label' => 'Rota de liquidação', 'value' => $settlementRoute->label()] : null,
                        ($settlementRoute && ($routeDetail = $settlementRoute->detail($tx))) ? ['label' => 'Detalhe rota', 'value' => $routeDetail, 'full' => true] : null,
                        $tx->partner_funding_status ? ['label' => 'Funding Wallester', 'value' => $partnerFundingLabels[$tx->partner_funding_status->value] ?? $tx->partner_funding_status->value] : null,
                        $tx->partner_funding_failure_reason ? ['label' => 'Erro funding', 'value' => $tx->partner_funding_failure_reason, 'full' => true] : null,
                        $tx->available_on ? ['label' => 'Disponível em', 'value' => $tx->available_on->format('d/m/Y')] : null,
                        $tx->balance_settled_at ? ['label' => 'Liquidado em', 'value' => $tx->balance_settled_at->format('d/m/Y H:i')] : null,
                        $tx->bank_transfer_sender_name ? ['label' => 'Remetente transferência', 'value' => $tx->bank_transfer_sender_name] : null,
                        $tx->bank_transfer_iban_last4 ? ['label' => 'IBAN (últimos 4)', 'value' => '···'.$tx->bank_transfer_iban_last4] : null,
                        $tx->description ? ['label' => 'Descrição', 'value' => $tx->description, 'full' => true] : null,
                    ], fn ($field) => $field !== null && filled($field['value'] ?? null)));

                    $stripeNetParts = [];
                    if ($tx->net_amount) {
                        $stripeNetParts[] = 'Líquido: '.number_format($tx->net_amount / 100, 2, ',', '.');
                    }
                    if ($tx->stripe_fee) {
                        $stripeNetParts[] = 'Stripe: '.number_format($tx->stripe_fee / 100, 2, ',', '.');
                    }
                    if ($tx->platform_fee) {
                        $stripeNetParts[] = 'Plataforma: '.number_format($tx->platform_fee / 100, 2, ',', '.');
                    }

                    $stripeDetails = [
                        'kind' => 'stripe',
                        'subtitle' => ($tx->type?->label() ?? 'Transação').' #'.$tx->id,
                        'isIncoming' => $isCredit,
                        'amountLabel' => ($isCredit ? '+' : '−').number_format(abs($tx->amount) / 100, 2, ',', '.').' '.strtoupper($tx->currency),
                        'netAmountLabel' => $stripeNetParts !== [] ? implode(' · ', $stripeNetParts) : null,
                        'customerUrl' => $tx->customer ? route('dashboard.customers.show', $tx->customer).'#transactions' : null,
                        'fields' => $stripeFields,
                        'metadata' => null,
                    ];
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
                    <td class="text-right whitespace-nowrap">
                        <button type="button"
                                @click="details = @js($stripeDetails); showDetailsModal = true"
                                class="text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                            Ver detalhes
                        </button>
                        @can('customers.view')
                        @if ($tx->customer)
                        <a href="{{ route('dashboard.customers.show', $tx->customer) }}#transactions"
                           class="text-xs text-slate-400 hover:text-indigo-600 hover:underline block mt-1">Ver cliente</a>
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
        <form method="GET" action="{{ $transactionsRoute }}" class="flex flex-wrap items-center gap-3">
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
            <a href="{{ $transactionsRoute }}?tab=wallester"
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
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                @php
                    $account = $movement->fundingAccount;
                    $customer = $account instanceof \App\Models\CustomerWallesterAccount ? $account->customer : null;
                    $direction = $movement->direction?->value;
                    $isIn = $direction === 'in';
                    $card = $movement->wallesterCard;
                    $fxDetailLabel = $movement->fxDetailLabel();

                    $wallesterFields = array_values(array_filter([
                        ['label' => 'Data', 'value' => $movement->created_at?->format('d/m/Y H:i')],
                        $customer ? ['label' => 'Cliente', 'value' => $customer->name, 'link' => route('dashboard.customers.show', $customer)] : null,
                        $customer?->email ? ['label' => 'Email', 'value' => $customer->email] : null,
                        ['label' => 'Conta Wallester', 'value' => $movement->external_account_id, 'mono' => true, 'full' => true],
                        ['label' => 'Tipo', 'value' => $movementTypeLabels[$movement->type?->value ?? ''] ?? ($movement->type?->value ?? '—')],
                        ['label' => 'Direção', 'value' => strtoupper($direction ?? '—')],
                        ['label' => 'Status', 'value' => $movementStatusLabels[$movement->status?->value ?? ''] ?? ($movement->status?->value ?? '—')],
                        ['label' => 'Origem', 'value' => $movementSourceLabels[$movement->source?->value ?? ''] ?? ($movement->source?->value ?? '—')],
                        $card?->name ? ['label' => 'Cartão', 'value' => $card->name.($card->masked_card_number ? ' ('.$card->masked_card_number.')' : '')] : null,
                        $movement->partner_external_id ? ['label' => 'ID parceiro', 'value' => $movement->partner_external_id, 'mono' => true, 'full' => true] : null,
                        $movement->idempotency_key ? ['label' => 'Idempotency', 'value' => $movement->idempotency_key, 'mono' => true, 'full' => true] : null,
                        $movement->transfer_group_id ? ['label' => 'Transfer group', 'value' => $movement->transfer_group_id, 'mono' => true, 'full' => true] : null,
                        $movement->counterparty_external_account_id ? ['label' => 'Contraparte', 'value' => $movement->counterparty_external_account_id, 'mono' => true, 'full' => true] : null,
                        $movement->failure_reason ? ['label' => 'Motivo falha', 'value' => $movement->failure_reason, 'full' => true] : null,
                        $movement->description ? ['label' => 'Descrição', 'value' => $movement->description, 'full' => true] : null,
                        $movement->original_amount && $movement->original_currency
                            ? ['label' => 'Compra', 'value' => number_format($movement->original_amount / 100, 2, ',', '.').' '.strtoupper($movement->original_currency)]
                            : null,
                        $movement->fees_amount
                            ? ['label' => 'Câmbio', 'value' => number_format($movement->fees_amount / 100, 2, ',', '.').' '.strtoupper($movement->currency)]
                            : null,
                        $movement->reference instanceof \App\Models\StripeTransaction
                            ? ['label' => 'Referência Stripe', 'value' => 'Transação #'.$movement->reference->id, 'link' => route('dashboard.transactions', ['tab' => 'stripe', 'search' => $movement->reference->id])]
                            : null,
                    ], fn ($field) => $field !== null && filled($field['value'] ?? null)));

                    $wallesterDetails = [
                        'kind' => 'wallester',
                        'subtitle' => ($movementTypeLabels[$movement->type?->value ?? ''] ?? 'Movimento').' #'.$movement->id,
                        'isIncoming' => $isIn,
                        'amountLabel' => ($isIn ? '+' : '−').number_format($movement->amount / 100, 2, ',', '.').' '.strtoupper($movement->currency),
                        'netAmountLabel' => null,
                        'customerUrl' => $customer ? route('dashboard.customers.show', $customer).'#transactions' : null,
                        'fields' => $wallesterFields,
                        'metadata' => filled($movement->metadata)
                            ? json_encode($movement->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                            : null,
                    ];
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
                        @if ($fxDetailLabel)
                        <span class="text-[11px] text-slate-400 block">{{ $fxDetailLabel }}</span>
                        @endif
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
                    <td class="text-right whitespace-nowrap">
                        <button type="button"
                                @click="details = @js($wallesterDetails); showDetailsModal = true"
                                class="text-xs font-medium text-violet-600 hover:text-violet-800 hover:underline">
                            Ver detalhes
                        </button>
                        @can('customers.view')
                        @if ($customer)
                        <a href="{{ route('dashboard.customers.show', $customer) }}#transactions"
                           class="text-xs text-slate-400 hover:text-violet-600 hover:underline block mt-1">Ver cliente</a>
                        @endif
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="py-12 text-center text-sm text-slate-400">
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

    @include('dashboard.transactions.partials.transaction-details-modal')

</div>

@endsection
