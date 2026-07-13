@php
    $incomingCount = $activityTimeline->filter(fn ($item) => $item->isIncoming)->count();
    $outgoingCount = $activityTimeline->filter(fn ($item) => ! $item->isIncoming)->count();
@endphp

<div id="transactions" class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Movimentos</h2>
                <p class="text-xs text-slate-500 mt-0.5">Entradas e saídas — Stripe e cartão Wallester</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <span class="text-[10px] font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-1 rounded-full">
                    +{{ $incomingCount }} entradas
                </span>
                <span class="text-[10px] font-medium text-red-600 bg-red-50 border border-red-200 px-2 py-1 rounded-full">
                    −{{ $outgoingCount }} saídas
                </span>
            </div>
        </div>
    </div>

    @if ($activityTimeline->isEmpty())
    <div class="px-5 py-10 text-center">
        <div class="flex flex-col items-center gap-2">
            <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <p class="text-sm text-slate-400">Nenhum movimento registado.</p>
        </div>
    </div>
    @else
    <div class="px-5 py-4">
        <div class="relative">
            <div class="absolute left-[7px] top-2 bottom-2 w-px bg-slate-200"></div>
            <div class="space-y-0">
                @foreach ($activityTimeline as $item)
                @php
                    $amount = number_format(abs($item->amountCents) / 100, 2, ',', '.');
                    $status = strtolower($item->status);
                    $statusBadge = match(true) {
                        in_array($status, ['succeeded', 'paid', 'complete', 'completed', 'settled', 'concluído', 'concluido']) => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        in_array($status, ['pending', 'processing', 'requires_capture', 'pendente']) => 'bg-amber-50 text-amber-700 border-amber-200',
                        in_array($status, ['failed', 'canceled', 'cancelled', 'refunded', 'disputed', 'falhou']) => 'bg-red-50 text-red-700 border-red-200',
                        default => 'bg-slate-100 text-slate-500 border-slate-200',
                    };
                    $sourceBadge = $item->source === 'wallester'
                        ? 'bg-violet-50 text-violet-700 border-violet-200'
                        : 'bg-indigo-50 text-indigo-700 border-indigo-200';
                @endphp
                <div class="relative flex gap-3 {{ $loop->last ? 'pb-0' : 'pb-3' }}">
                    <div class="relative flex-shrink-0 mt-1">
                        <div class="w-3.5 h-3.5 rounded-full border-2 border-white shadow-sm z-10 relative
                                    {{ $item->isIncoming ? 'bg-emerald-500' : 'bg-red-400' }}"></div>
                    </div>
                    <div class="flex-1 min-w-0 pb-3 {{ ! $loop->last ? 'border-b border-slate-50' : '' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
                                    <span class="text-sm font-bold {{ $item->isIncoming ? 'text-emerald-600' : 'text-red-500' }}">
                                        {{ $item->isIncoming ? '+' : '−' }} {{ $amount }} {{ $item->currency }}
                                    </span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium border {{ $sourceBadge }}">
                                        {{ $item->sourceLabel }}
                                    </span>
                                    @if ($item->netAmountCents && $item->netAmountCents !== $item->amountCents)
                                    <span class="text-xs text-slate-400">(líq. {{ number_format($item->netAmountCents / 100, 2, ',', '.') }})</span>
                                    @endif
                                </div>

                                <p class="text-xs font-medium text-slate-700">{{ $item->typeLabel }}</p>

                                @if ($item->description)
                                <p class="text-xs text-slate-600 truncate mt-0.5">{{ $item->description }}</p>
                                @endif

                                @if ($item->source === 'wallester' && ($item->cardLabel || $item->merchantCity || $item->merchantCategoryCode))
                                <p class="text-xs text-slate-400 mt-0.5">
                                    @if ($item->cardLabel)
                                    <span>Cartão: {{ $item->cardLabel }}</span>
                                    @endif
                                    @if ($item->merchantCity)
                                    <span class="{{ $item->cardLabel ? 'ml-2' : '' }}">{{ $item->merchantCity }}</span>
                                    @endif
                                    @if ($item->merchantCategoryCode)
                                    <span class="ml-2">MCC {{ $item->merchantCategoryCode }}</span>
                                    @endif
                                </p>
                                @endif

                                @if ($item->stripeTransaction)
                                <div class="mt-1">
                                    @include('dashboard.partials.topup-settlement-route', ['transaction' => $item->stripeTransaction])
                                </div>
                                @endif

                                <p class="text-xs text-slate-400 mt-0.5 flex items-baseline gap-1.5 min-w-0">
                                    <span class="flex-shrink-0 text-slate-500">
                                        {{ $item->occurredAt->format('d/m/Y') }}
                                        <span class="text-slate-300">{{ $item->occurredAt->format('H:i') }}</span>
                                    </span>
                                </p>

                                @if ($item->bankTransferSenderName)
                                <p class="text-xs text-slate-400 mt-0.5">
                                    De: {{ $item->bankTransferSenderName }}
                                    @if ($item->bankTransferIbanLast4)(···{{ $item->bankTransferIbanLast4 }})@endif
                                </p>
                                @endif

                                @if ($item->wallesterMovement?->partner_external_id)
                                <p class="text-[10px] text-slate-300 mt-0.5 font-mono truncate">
                                    {{ $item->wallesterMovement->partner_external_id }}
                                </p>
                                @endif
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium border {{ $statusBadge }}">
                                    {{ $item->status }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
