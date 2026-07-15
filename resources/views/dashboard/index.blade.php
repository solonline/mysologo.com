@extends('layouts.dashboard')

@section('title', 'Dashboard')
@section('breadcrumb', 'Visão Geral')

@section('page-header')
<div class="flex items-start justify-between gap-4">
    <div>
        <h1 class="page-title">Bom dia, {{ auth()->user()?->name ?? 'Admin' }} 👋</h1>
        <p class="page-subtitle">{{ now()->translatedFormat('l, d \d\e F \d\e Y') }}</p>
    </div>
    <div class="hidden sm:flex items-center gap-2.5 flex-shrink-0">
        @if ($stats['maintenance_mode'])
        <a href="{{ route('dashboard.settings') }}" title="Clique para gerir"
           class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold"
           style="background:#fffbeb;border:1px solid #fcd34d;color:#92400e;">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
            </span>
            Manutenção activa
        </a>
        @else
        <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold"
              style="background:#f0fdf4;border:1px solid #bbf7d0;color:#065f46;">
            <span class="w-2 h-2 rounded-full bg-emerald-500" style="box-shadow:0 0 4px rgba(16,185,129,.5)"></span>
            API operacional
        </span>
        @endif

        <a href="#" class="btn-secondary btn-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Exportar
        </a>
        <a href="{{ route('dashboard.customers') }}" class="btn-primary btn-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Novo Cliente
        </a>
    </div>
</div>
@endsection

@section('content')

@php
    $fundingCounts = $wallesterTopupFundingCounts ?? ['awaiting_liquidity' => 0, 'failed' => 0, 'pending' => 0];
    $blockedTopups = ($fundingCounts['awaiting_liquidity'] ?? 0) + ($fundingCounts['failed'] ?? 0) + ($fundingCounts['pending'] ?? 0);
@endphp

<div x-data="{ showWallesterFundingModal: false }">

@if ($stats['maintenance_mode'])
<div class="mb-7 flex items-start gap-4 px-5 py-4 rounded-2xl"
     style="background:#fffbeb;border:1px solid #fcd34d;">
    <div class="flex-shrink-0 w-9 h-9 rounded-xl bg-amber-100 flex items-center justify-center mt-0.5">
        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-amber-800">Modo de manutenção activo</p>
        <p class="text-xs text-amber-700 mt-0.5">A API está bloqueada para todos os clientes. Desactive em <a href="{{ route('dashboard.settings') }}" class="underline font-semibold hover:text-amber-900">Configurações → Sistema</a>.</p>
    </div>
</div>
@endif

@if (($stats['provisioning_failures'] ?? 0) > 0)
<div class="mb-7 flex items-start gap-4 px-5 py-4 rounded-2xl"
     style="background:#fef2f2;border:1px solid #fecaca;">
    <div class="flex-shrink-0 w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center mt-0.5">
        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-red-800">{{ $stats['provisioning_failures'] }} falha(s) de provisioning Wallester</p>
        <p class="text-xs text-red-700 mt-0.5">Clientes em provisioning com conta Wallester em estado FAILED. Revise em <a href="{{ route('dashboard.customers', ['status' => 'PROVISIONING']) }}" class="underline font-semibold hover:text-red-900">Clientes</a>.</p>
    </div>
</div>
@endif

{{-- ══════════════════════════ STAT CARDS ══════════════════════════ --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">

    {{-- Total de Movimentos --}}
    <a href="{{ route('dashboard.transactions', ['tab' => 'wallester']) }}"
       class="card p-5 block transition-all hover:shadow-md hover:border-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-300/40 group">
        <div class="flex items-start justify-between mb-4">
            <div class="p-2.5 rounded-xl stat-icon-1">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-slate-900 tracking-tight">{{ number_format($stats['total_movements'] ?? 0) }}</p>
        <p class="text-sm text-slate-500 mt-1 font-medium">Total de Movimentos</p>
        <div class="mt-4 grid grid-cols-3 gap-2">
            <div class="rounded-lg bg-emerald-50 border border-emerald-100 px-2 py-1.5 text-center">
                <p class="text-[10px] font-semibold text-emerald-700 uppercase">OK</p>
                <p class="text-sm font-bold text-emerald-900">{{ number_format($stats['movements_completed'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg bg-amber-50 border border-amber-100 px-2 py-1.5 text-center">
                <p class="text-[10px] font-semibold text-amber-700 uppercase">Pend.</p>
                <p class="text-sm font-bold text-amber-900">{{ number_format($stats['movements_pending'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg bg-red-50 border border-red-100 px-2 py-1.5 text-center">
                <p class="text-[10px] font-semibold text-red-700 uppercase">Falha</p>
                <p class="text-sm font-bold text-red-900">{{ number_format($stats['movements_failed'] ?? 0) }}</p>
            </div>
        </div>
        <span class="text-xs font-semibold text-violet-600 opacity-0 group-hover:opacity-100 transition-opacity mt-3 inline-block">Ver movimentos →</span>
    </a>

    {{-- Clientes --}}
    <a href="{{ route('dashboard.customers') }}"
       class="card p-5 block transition-all hover:shadow-md hover:border-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-300/40 group">
        <div class="flex items-start justify-between mb-4">
            <div class="p-2.5 rounded-xl stat-icon-2">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <span class="text-xs font-semibold text-violet-600 opacity-0 group-hover:opacity-100 transition-opacity">Ver todos →</span>
        </div>
        <p class="text-3xl font-bold text-slate-900 tracking-tight">{{ number_format($stats['total_customers'] ?? 0) }}</p>
        <p class="text-sm text-slate-500 mt-1 font-medium">Clientes</p>
        <div class="mt-4 space-y-2">
            @php
                $customerBreakdown = [
                    ['label' => 'Ativos', 'value' => $stats['active_customers'] ?? 0, 'color' => '#10b981'],
                    ['label' => 'Pendentes', 'value' => $stats['pending_customers'] ?? 0, 'color' => '#f59e0b'],
                    ['label' => 'Em análise', 'value' => $stats['analysis_customers'] ?? 0, 'color' => '#0ea5e9'],
                    ['label' => 'Bloqueados', 'value' => $stats['blocked_customers'] ?? 0, 'color' => '#ef4444'],
                    ['label' => 'Docs pendentes', 'value' => $stats['pending_documents'] ?? 0, 'color' => '#64748b'],
                ];
            @endphp
            @foreach ($customerBreakdown as $row)
            <div class="flex items-center justify-between text-xs">
                <span class="flex items-center gap-1.5 text-slate-600">
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:{{ $row['color'] }}"></span>
                    {{ $row['label'] }}
                </span>
                <span class="font-bold text-slate-800">{{ number_format($row['value']) }}</span>
            </div>
            @endforeach
        </div>
    </a>

    {{-- Wallester --}}
    @php
        $wallesterAccounts = $wallesterMainAccounts ?? collect();
        $wallesterPrimaryAccount = $wallesterAccounts->first();
    @endphp
    <a href="#"
       @click.prevent="showWallesterFundingModal = true"
       class="card p-5 block transition-all hover:shadow-md hover:border-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-300/40 group">
        <div class="flex items-start justify-between mb-4">
            <div class="p-2.5 rounded-xl stat-icon-3">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
            </div>
            @if ($blockedTopups > 0)
            <span class="flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full" style="background:#fffbeb;color:#d97706;">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                {{ $blockedTopups }} bloqueado{{ $blockedTopups !== 1 ? 's' : '' }}
            </span>
            @else
            <span class="text-xs font-semibold text-violet-600 opacity-0 group-hover:opacity-100 transition-opacity">Gerir →</span>
            @endif
        </div>

        @if ($wallesterPrimaryAccount === null)
        <p class="text-3xl font-bold text-slate-400 tracking-tight">—</p>
        @else
        @php $primaryCurrency = strtoupper($wallesterPrimaryAccount->funding_currency ?: $wallesterPrimaryAccount->currency_code); @endphp
        <p class="text-3xl font-bold text-slate-900 tracking-tight">
            {{ number_format(((int) $wallesterPrimaryAccount->available_amount) / 100, 2, ',', '.') }}
            <span class="text-sm font-semibold text-slate-500">{{ $primaryCurrency }}</span>
        </p>
        @endif
        <p class="text-sm text-slate-500 mt-1 font-medium">Wallester · Main</p>
        @if ($wallesterAccounts->count() > 1 || $blockedTopups > 0)
        <div class="mt-4 space-y-2">
            @foreach ($wallesterAccounts->skip(1) as $mainAccount)
            @php $currency = strtoupper($mainAccount->funding_currency ?: $mainAccount->currency_code); @endphp
            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-600">{{ $currency }}</span>
                <span class="font-bold text-slate-800">
                    {{ number_format(((int) $mainAccount->available_amount) / 100, 2, ',', '.') }} {{ $currency }}
                </span>
            </div>
            @endforeach
            @if ($blockedTopups > 0)
            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-600">À espera de liquidez</span>
                <span class="font-bold text-amber-700">{{ $fundingCounts['awaiting_liquidity'] ?? 0 }}</span>
            </div>
            @endif
        </div>
        @endif
    </a>

    {{-- Receita Platform --}}
    @can('transactions.view')
    @php $platformRevenuePrimary = $platformRevenue->primary(); @endphp
    <a href="{{ route('dashboard.platform-revenue') }}"
       class="card p-5 block transition-all hover:shadow-md hover:border-emerald-200 focus:outline-none focus:ring-2 focus:ring-emerald-300/40 group">
        <div class="flex items-start justify-between mb-4">
            <div class="p-2.5 rounded-xl stat-icon-4">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            @if ($platformRevenuePrimary && ! $platformRevenue->ledgerMatchesStripe)
            <span class="flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full" style="background:#fffbeb;color:#d97706;">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                Divergência
            </span>
            @else
            <span class="text-xs font-semibold text-emerald-600 opacity-0 group-hover:opacity-100 transition-opacity">Ver detalhe →</span>
            @endif
        </div>

        @if ($platformRevenuePrimary === null)
        <p class="text-3xl font-bold text-slate-400 tracking-tight">—</p>
        @else
        <p class="text-3xl font-bold text-slate-900 tracking-tight">
            {{ number_format($platformRevenuePrimary->accruedCents / 100, 2, ',', '.') }}
            <span class="text-sm font-semibold text-slate-500">{{ $platformRevenuePrimary->currency }}</span>
        </p>
        @endif
        <p class="text-sm text-slate-500 mt-1 font-medium">Receita Yoop · ganhos com taxas</p>
        @if ($platformRevenuePrimary)
        <div class="mt-4 space-y-2">
            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-600">Este mês</span>
                <span class="font-bold text-slate-800">{{ number_format($platformRevenuePrimary->monthAccruedCents / 100, 2, ',', '.') }} {{ $platformRevenuePrimary->currency }}</span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-600">Transações com taxa</span>
                <span class="font-bold text-slate-800">{{ number_format($platformRevenuePrimary->transactionCount) }}</span>
            </div>
        </div>
        @endif
    </a>
    @endcan

</div>

{{-- ══════════════════════════ MAIN GRID ══════════════════════════ --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    {{-- ── Clientes Recentes ── --}}
    <div class="xl:col-span-2 card overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="border-bottom:1px solid #f1f5f9;">
            <div>
                <h2 class="section-title">Clientes Recentes</h2>
                <p class="section-sub">Últimos cadastros na plataforma</p>
            </div>
            <a href="{{ route('dashboard.customers') }}"
               class="flex items-center gap-1 text-xs font-semibold transition-colors"
               style="color:#7c3aed"
               onmouseenter="this.style.color='#6d28d9'"
               onmouseleave="this.style.color='#7c3aed'">
                Ver todos
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full data-table">
                <thead>
                    <tr>
                        <th class="text-left">Cliente</th>
                        <th class="text-left hidden sm:table-cell">Telefone</th>
                        <th class="text-left">Documentos</th>
                        <th class="text-left">Status</th>
                        <th class="text-left hidden md:table-cell">Cadastro</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentCustomers as $customer)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                                     style="background:linear-gradient(135deg,#7c3aed,#a855f7);">
                                    {{ strtoupper(substr($customer->name ?? 'C', 0, 2)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 leading-tight">{{ $customer->name }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $customer->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="hidden sm:table-cell">
                            <span class="text-sm text-slate-600">{{ $customer->phone ?? '—' }}</span>
                        </td>
                        <td>
                            @php
                                $docVal = $customer->doc_status?->value ?? $customer->doc_status ?? '';
                                $docMap = match(strtoupper($docVal)) {
                                    'APPROVED'  => ['bg:#f0fdf4;color:#059669;border-color:#bbf7d0',  'bg-emerald-500', 'Aprovado'],
                                    'PENDING'   => ['bg:#fffbeb;color:#d97706;border-color:#fde68a',  'bg-amber-500',   'Pendente'],
                                    'ANALYSING' => ['bg:#eff6ff;color:#2563eb;border-color:#bfdbfe',  'bg-sky-500',     'Em Análise'],
                                    'DECLINED'  => ['bg:#fef2f2;color:#dc2626;border-color:#fecaca',  'bg-red-500',     'Recusado'],
                                    default     => ['bg:#f8fafc;color:#64748b;border-color:#e2e8f0',  'bg-slate-400',   $docVal ?: 'N/A'],
                                };
                            @endphp
                            <span class="badge" style="{{ $docMap[0] }};border-color:{{ explode(';', $docMap[0])[2] ?? '' }}">
                                <span class="badge-dot {{ $docMap[1] }}"></span>
                                {{ $docMap[2] }}
                            </span>
                        </td>
                        <td>
                            @php
                                $custVal = $customer->status?->value ?? $customer->status ?? '';
                                $custMap = match(strtoupper($custVal)) {
                                    'ACTIVE'   => ['bg:#f0fdf4;color:#059669;border-color:#bbf7d0',  'bg-emerald-500', 'Ativo'],
                                    'PENDING'  => ['bg:#fffbeb;color:#d97706;border-color:#fde68a',  'bg-amber-500',   'Pendente'],
                                    'ANALYSIS' => ['bg:#eff6ff;color:#2563eb;border-color:#bfdbfe',  'bg-sky-500',     'Em Análise'],
                                    'BLOCKED'  => ['bg:#fef2f2;color:#dc2626;border-color:#fecaca',  'bg-red-500',     'Bloqueado'],
                                    'INACTIVE' => ['bg:#f8fafc;color:#64748b;border-color:#e2e8f0',  'bg-slate-400',   'Inativo'],
                                    default    => ['bg:#f8fafc;color:#64748b;border-color:#e2e8f0',  'bg-slate-400',   $custVal ?: 'N/A'],
                                };
                            @endphp
                            <span class="badge" style="{{ $custMap[0] }}">
                                <span class="badge-dot {{ $custMap[1] }}"></span>
                                {{ $custMap[2] }}
                            </span>
                        </td>
                        <td class="hidden md:table-cell">
                            <span class="text-sm text-slate-500">{{ $customer->created_at?->format('d/m/Y') }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-14 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-14 h-14 rounded-2xl bg-slate-50 flex items-center justify-center">
                                    <svg class="w-7 h-7 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <p class="text-sm text-slate-400 font-medium">Nenhum cliente encontrado</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Right column ── --}}
    <div class="flex flex-col gap-6">

        {{-- Quick actions --}}
        <div class="card p-5">
            <div class="mb-5">
                <h2 class="section-title">Ações Rápidas</h2>
                <p class="section-sub">Acesso directo às principais áreas</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                @php
                    $actions = [
                        ['href' => route('dashboard.customers'), 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'label' => 'Clientes', 'bg' => '#ede9fe', 'icon_color' => '#7c3aed', 'hover_bg' => '#ddd6fe'],
                        ['href' => '#',                          'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'label' => 'Cartões', 'bg' => '#e0f2fe', 'icon_color' => '#0284c7', 'hover_bg' => '#bae6fd'],
                        ['href' => '#',                          'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'label' => 'Transações', 'bg' => '#d1fae5', 'icon_color' => '#059669', 'hover_bg' => '#a7f3d0'],
                        ['href' => '#',                          'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'label' => 'KYC', 'bg' => '#fef3c7', 'icon_color' => '#d97706', 'hover_bg' => '#fde68a'],
                    ];
                @endphp
                @foreach ($actions as $a)
                <a href="{{ $a['href'] }}"
                   class="flex flex-col items-center gap-2.5 p-3.5 rounded-xl border border-transparent transition-all cursor-pointer group"
                   style="background:{{ $a['bg'] }}"
                   onmouseenter="this.style.background='{{ $a['hover_bg'] }}'"
                   onmouseleave="this.style.background='{{ $a['bg'] }}'">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center transition-transform group-hover:scale-110"
                         style="background:rgba(255,255,255,.7);">
                        <svg class="w-5 h-5" style="color:{{ $a['icon_color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $a['icon'] }}"/>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold text-center" style="color:#374151">{{ $a['label'] }}</span>
                </a>
                @endforeach
            </div>
        </div>

        {{-- Platform Customers --}}
        <div class="card p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="section-title">Contas Platform</h2>
                    <p class="section-sub">{{ $stats['platform_customers'] }} conta{{ $stats['platform_customers'] !== 1 ? 's' : '' }} registada{{ $stats['platform_customers'] !== 1 ? 's' : '' }}</p>
                </div>
                <span class="p-2 rounded-xl" style="background:#f5f3ff;">
                    <svg class="w-4 h-4" style="color:#7c3aed" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </span>
            </div>
            @if ($platformCustomers->isEmpty())
                <p class="text-xs text-slate-400 text-center py-4">Nenhuma conta platform</p>
            @else
                <div class="space-y-2.5">
                    @foreach ($platformCustomers as $p)
                    @php
                        $pStatus = strtoupper($p->status?->value ?? $p->status ?? '');
                        $pColor  = match($pStatus) {
                            'ACTIVE'   => '#10b981',
                            'PENDING'  => '#f59e0b',
                            'BLOCKED'  => '#ef4444',
                            default    => '#94a3b8',
                        };
                    @endphp
                    <a href="{{ route('dashboard.customers.show', $p) }}"
                       class="flex items-center gap-3 rounded-xl px-2 py-1.5 -mx-2 transition-colors hover:bg-slate-50 group">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                             style="background:linear-gradient(135deg,#7c3aed,#a855f7);">
                            {{ strtoupper(substr($p->name ?? 'P', 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-slate-800 truncate group-hover:text-violet-700 transition-colors">{{ $p->name }}</p>
                            <p class="text-xs text-slate-400 truncate">{{ $p->email }}</p>
                        </div>
                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:{{ $pColor }}"></span>
                    </a>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

</div>

{{-- Modal: Funding Wallester --}}
<div x-show="showWallesterFundingModal" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-md" @click="showWallesterFundingModal = false"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">
        <div class="px-6 py-5 border-b border-slate-100 flex items-start justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Funding Wallester</h3>
                <p class="text-xs text-slate-500 mt-1">Saldo das contas main e reprocessamento de top-ups bloqueados.</p>
            </div>
            <button type="button" @click="showWallesterFundingModal = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
            @forelse ($wallesterMainAccounts ?? [] as $mainAccount)
            @php $currency = strtoupper($mainAccount->funding_currency ?: $mainAccount->currency_code); @endphp
            <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-slate-50 border border-slate-200 rounded-xl">
                <div>
                    <p class="text-sm font-semibold text-slate-800">Main {{ $currency }}</p>
                    <p class="text-xs text-slate-500 mt-0.5 font-mono">{{ $mainAccount->wallester_account_id }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-500 uppercase tracking-wider">Disponível</p>
                    <p class="text-lg font-bold text-slate-900">
                        {{ number_format(((int) $mainAccount->available_amount) / 100, 2, ',', '.') }}
                        {{ $currency }}
                    </p>
                </div>
            </div>
            @empty
            <p class="text-sm text-slate-500 text-center py-4">Nenhuma conta main configurada.</p>
            @endforelse

            <div class="grid grid-cols-3 gap-3">
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-center">
                    <p class="text-xs font-semibold text-amber-700 uppercase">À espera</p>
                    <p class="text-2xl font-bold text-amber-900">{{ $fundingCounts['awaiting_liquidity'] ?? 0 }}</p>
                </div>
                <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-center">
                    <p class="text-xs font-semibold text-red-700 uppercase">Falhados</p>
                    <p class="text-2xl font-bold text-red-900">{{ $fundingCounts['failed'] ?? 0 }}</p>
                </div>
                <div class="p-3 rounded-xl bg-blue-50 border border-blue-200 text-center">
                    <p class="text-xs font-semibold text-blue-700 uppercase">Pendentes</p>
                    <p class="text-2xl font-bold text-blue-900">{{ $fundingCounts['pending'] ?? 0 }}</p>
                </div>
            </div>
        </div>

        @can('settings.edit')
        <div class="px-6 py-5 border-t border-slate-100 bg-slate-50/50">
            <form method="POST" action="{{ route('dashboard.operations.retry-wallester-topups') }}"
                  onsubmit="return confirm('Sincronizar main e reprocessar top-ups elegíveis?');">
                @csrf
                <button type="submit"
                        class="w-full px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
                    Actualizar main e reprocessar top-ups
                </button>
            </form>
            <p class="text-xs text-slate-400 text-center mt-3">Sincroniza saldos via API Wallester e reenvia jobs de funding.</p>
        </div>
        @else
        <div class="px-6 py-5 border-t border-slate-100">
            <p class="text-xs text-slate-500 text-center">Sem permissão para reprocessar top-ups.</p>
        </div>
        @endcan
    </div>
</div>

</div>

@endsection
