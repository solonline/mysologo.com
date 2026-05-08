@extends('layouts.dashboard')

@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('page-header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Bom dia, {{ auth()->user()?->name ?? 'Admin' }} 👋</h1>
        <p class="mt-1 text-sm text-slate-500">Aqui está um resumo do seu sistema hoje, {{ now()->format('d \d\e F \d\e Y') }}.</p>
    </div>
    <div class="hidden sm:flex items-center gap-3">
        {{-- Maintenance mode status --}}
        @if ($stats['maintenance_mode'])
        <a href="{{ route('dashboard.settings') }}" title="Clique para gerir"
           class="flex items-center gap-2 px-3 py-1.5 bg-amber-50 border border-amber-300 rounded-xl text-xs font-semibold text-amber-700 hover:bg-amber-100 transition-colors">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
            </span>
            Manutenção activa
        </a>
        @else
        <span class="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 border border-emerald-200 rounded-xl text-xs font-semibold text-emerald-700">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            API operacional
        </span>
        @endif

        <button class="flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 shadow-sm transition-colors">
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Exportar
        </button>
        <button class="flex items-center gap-2 px-4 py-2 bg-indigo-600 rounded-xl text-sm font-medium text-white hover:bg-indigo-700 shadow-sm shadow-indigo-500/30 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Novo Cliente
        </button>
    </div>
</div>
@endsection

@section('content')

@if ($stats['maintenance_mode'])
<div class="mb-6 flex items-start gap-4 px-5 py-4 bg-amber-50 border border-amber-300 rounded-2xl">
    <div class="flex-shrink-0 mt-0.5 p-1.5 bg-amber-100 rounded-lg">
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

{{-- ==================== STAT CARDS ==================== --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">

    {{-- Card 1 --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/60 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between mb-4">
            <div class="p-2.5 rounded-xl stat-card-gradient-1 shadow-lg shadow-indigo-500/25">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <span class="flex items-center gap-1 text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                </svg>
                +12%
            </span>
        </div>
        <p class="text-2xl font-bold text-slate-900">{{ number_format($stats['total_customers'] ?? 0) }}</p>
        <p class="text-sm text-slate-500 mt-1">Total de Clientes</p>
        <div class="mt-3 h-1 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-indigo-500 rounded-full" style="width: 72%"></div>
        </div>
    </div>

    {{-- Card 2 --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/60 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between mb-4">
            <div class="p-2.5 rounded-xl stat-card-gradient-2 shadow-lg shadow-sky-500/25">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <span class="flex items-center gap-1 text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                </svg>
                +5%
            </span>
        </div>
        <p class="text-2xl font-bold text-slate-900">{{ number_format($stats['active_customers'] ?? 0) }}</p>
        <p class="text-sm text-slate-500 mt-1">Clientes Ativos</p>
        <div class="mt-3 h-1 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-sky-500 rounded-full" style="width: 55%"></div>
        </div>
    </div>

    {{-- Card 3 --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/60 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between mb-4">
            <div class="p-2.5 rounded-xl stat-card-gradient-3 shadow-lg shadow-emerald-500/25">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <span class="flex items-center gap-1 text-xs font-semibold text-red-600 bg-red-50 px-2 py-1 rounded-full">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
                -2%
            </span>
        </div>
        <p class="text-2xl font-bold text-slate-900">{{ number_format($stats['pending_documents'] ?? 0) }}</p>
        <p class="text-sm text-slate-500 mt-1">Documentos Pendentes</p>
        <div class="mt-3 h-1 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-emerald-500 rounded-full" style="width: 38%"></div>
        </div>
    </div>

    {{-- Card 4 --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/60 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between mb-4">
            <div class="p-2.5 rounded-xl stat-card-gradient-4 shadow-lg shadow-amber-500/25">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <span class="flex items-center gap-1 text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                </svg>
                +18%
            </span>
        </div>
        <p class="text-2xl font-bold text-slate-900">R$ {{ number_format($stats['total_balance'] ?? 0, 2, ',', '.') }}</p>
        <p class="text-sm text-slate-500 mt-1">Volume Total</p>
        <div class="mt-3 h-1 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-amber-500 rounded-full" style="width: 83%"></div>
        </div>
    </div>

</div>

{{-- ==================== MAIN GRID ==================== --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    {{-- Recent Customers Table --}}
    <div class="xl:col-span-2 bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Clientes Recentes</h2>
                <p class="text-xs text-slate-500 mt-0.5">Últimos clientes cadastrados</p>
            </div>
            <a href="{{ route('dashboard.customers') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-700 transition-colors">
                Ver todos →
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50/60">
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-6 py-3">Cliente</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 hidden sm:table-cell">Telefone</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Doc Status</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Status</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 hidden md:table-cell">Criado em</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentCustomers as $customer)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                    {{ strtoupper(substr($customer->name ?? 'C', 0, 2)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900">{{ $customer->name }}</p>
                                    <p class="text-xs text-slate-400">{{ $customer->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 hidden sm:table-cell">
                            <span class="text-sm text-slate-600">{{ $customer->phone ?? '—' }}</span>
                        </td>
                        {{-- doc_status: DocumentStatus enum (PENDING, ANALYSING, APPROVED, DECLINED) --}}
                        <td class="px-4 py-3.5">
                            @php
                                $docVal = $customer->doc_status?->value ?? $customer->doc_status ?? '';
                                $docBadge = match(strtoupper($docVal)) {
                                    'APPROVED'  => ['badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Aprovado'],
                                    'PENDING'   => ['badge' => 'bg-amber-50 text-amber-700 border-amber-200',     'dot' => 'bg-amber-500',   'label' => 'Pendente'],
                                    'ANALYSING' => ['badge' => 'bg-sky-50 text-sky-700 border-sky-200',           'dot' => 'bg-sky-500',     'label' => 'Em Análise'],
                                    'DECLINED'  => ['badge' => 'bg-red-50 text-red-700 border-red-200',           'dot' => 'bg-red-500',     'label' => 'Recusado'],
                                    default     => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200',    'dot' => 'bg-slate-400',   'label' => $docVal ?: 'N/A'],
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $docBadge['badge'] }}">
                                <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $docBadge['dot'] }}"></span>
                                {{ $docBadge['label'] }}
                            </span>
                        </td>
                        {{-- status: CustomerStatus enum (PENDING, ANALYSIS, ACTIVE, INACTIVE, BLOCKED) --}}
                        <td class="px-4 py-3.5">
                            @php
                                $custVal = $customer->status?->value ?? $customer->status ?? '';
                                $custBadge = match(strtoupper($custVal)) {
                                    'ACTIVE'   => ['badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Ativo'],
                                    'PENDING'  => ['badge' => 'bg-amber-50 text-amber-700 border-amber-200',     'dot' => 'bg-amber-500',   'label' => 'Pendente'],
                                    'ANALYSIS' => ['badge' => 'bg-sky-50 text-sky-700 border-sky-200',           'dot' => 'bg-sky-500',     'label' => 'Em Análise'],
                                    'BLOCKED'  => ['badge' => 'bg-red-50 text-red-700 border-red-200',           'dot' => 'bg-red-500',     'label' => 'Bloqueado'],
                                    'INACTIVE' => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200',    'dot' => 'bg-slate-400',   'label' => 'Inativo'],
                                    default    => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200',    'dot' => 'bg-slate-400',   'label' => $custVal ?: 'N/A'],
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $custBadge['badge'] }}">
                                <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $custBadge['dot'] }}"></span>
                                {{ $custBadge['label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 hidden md:table-cell">
                            <span class="text-sm text-slate-500">{{ $customer->created_at?->format('d/m/Y') }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <p class="text-sm text-slate-400 font-medium">Nenhum cliente encontrado</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Sidebar panels --}}
    <div class="flex flex-col gap-6">

        {{-- Status breakdown --}}
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-5">
            <h2 class="text-base font-semibold text-slate-900 mb-4">Status dos Clientes</h2>
            <div class="space-y-3">
                @php
                    $statuses = [
                        ['label' => 'Ativos',      'value' => $stats['active_customers'] ?? 0,   'color' => 'bg-emerald-500', 'pct' => 68],
                        ['label' => 'Pendentes',  'value' => $stats['pending_customers'] ?? 0,  'color' => 'bg-amber-400',   'pct' => 20],
                        ['label' => 'Em Análise', 'value' => $stats['analysis_customers'] ?? 0, 'color' => 'bg-sky-400',     'pct' => 8],
                        ['label' => 'Bloqueados', 'value' => $stats['blocked_customers'] ?? 0,  'color' => 'bg-red-400',     'pct' => 4],
                    ];
                @endphp
                @foreach ($statuses as $s)
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-sm text-slate-700 font-medium">{{ $s['label'] }}</span>
                        <span class="text-sm font-semibold text-slate-900">{{ number_format($s['value']) }}</span>
                    </div>
                    <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full {{ $s['color'] }} rounded-full transition-all duration-700"
                             style="width: {{ $s['pct'] }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Quick actions --}}
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-5">
            <h2 class="text-base font-semibold text-slate-900 mb-4">Ações Rápidas</h2>
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('dashboard.customers') }}" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-slate-50 hover:bg-indigo-50 hover:border-indigo-200 border border-slate-100 transition-all cursor-pointer group">
                    <div class="w-9 h-9 rounded-lg bg-indigo-100 group-hover:bg-indigo-200 flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-slate-700 text-center">Clientes</span>
                </a>
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-slate-50 hover:bg-sky-50 hover:border-sky-200 border border-slate-100 transition-all cursor-pointer group">
                    <div class="w-9 h-9 rounded-lg bg-sky-100 group-hover:bg-sky-200 flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-slate-700 text-center">Cartões</span>
                </a>
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-slate-50 hover:bg-emerald-50 hover:border-emerald-200 border border-slate-100 transition-all cursor-pointer group">
                    <div class="w-9 h-9 rounded-lg bg-emerald-100 group-hover:bg-emerald-200 flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-slate-700 text-center">Transações</span>
                </a>
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-xl bg-slate-50 hover:bg-amber-50 hover:border-amber-200 border border-slate-100 transition-all cursor-pointer group">
                    <div class="w-9 h-9 rounded-lg bg-amber-100 group-hover:bg-amber-200 flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-slate-700 text-center">KYC</span>
                </a>
            </div>
        </div>

    </div>
</div>

@endsection
