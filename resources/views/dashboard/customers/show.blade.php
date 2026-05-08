@extends('layouts.dashboard')

@section('title', $customer->name)
@section('breadcrumb', 'Clientes')

@section('page-header')
<div class="flex items-center gap-4">
    <a href="{{ route('dashboard.customers') }}"
       class="p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-400 to-violet-500
                    flex items-center justify-center text-white text-base font-bold shadow-md shadow-indigo-500/20">
            {{ strtoupper(substr($customer->name ?? 'C', 0, 2)) }}
        </div>
        <div>
            <h1 class="text-xl font-bold text-slate-900">{{ $customer->name }}</h1>
            <p class="text-sm text-slate-500">{{ $customer->email }}</p>
        </div>
    </div>
</div>
@endsection

@section('content')

@php
    $custVal = $customer->status?->value ?? $customer->status ?? '';
    $custBadge = match(strtoupper($custVal)) {
        'ACTIVE'   => ['badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Ativo'],
        'PENDING'  => ['badge' => 'bg-amber-50 text-amber-700 border-amber-200',       'dot' => 'bg-amber-500',   'label' => 'Pendente'],
        'ANALYSIS' => ['badge' => 'bg-sky-50 text-sky-700 border-sky-200',             'dot' => 'bg-sky-500',     'label' => 'Em Análise'],
        'BLOCKED'  => ['badge' => 'bg-red-50 text-red-700 border-red-200',             'dot' => 'bg-red-500',     'label' => 'Bloqueado'],
        'INACTIVE' => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200',      'dot' => 'bg-slate-400',   'label' => 'Inativo'],
        default    => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200',      'dot' => 'bg-slate-400',   'label' => $custVal ?: '—'],
    };
    $docVal = $customer->doc_status?->value ?? $customer->doc_status ?? '';
    $docBadge = match(strtoupper($docVal)) {
        'APPROVED'  => ['badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Aprovado'],
        'PENDING'   => ['badge' => 'bg-amber-50 text-amber-700 border-amber-200',       'dot' => 'bg-amber-500',   'label' => 'Pendente'],
        'ANALYSING' => ['badge' => 'bg-sky-50 text-sky-700 border-sky-200',             'dot' => 'bg-sky-500',     'label' => 'Em Análise'],
        'DECLINED'  => ['badge' => 'bg-red-50 text-red-700 border-red-200',             'dot' => 'bg-red-500',     'label' => 'Recusado'],
        default     => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200',      'dot' => 'bg-slate-400',   'label' => $docVal ?: '—'],
    };
@endphp

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    {{-- ==================== LEFT COLUMN ==================== --}}
    <div class="xl:col-span-2 flex flex-col gap-6">

        {{-- Informações Gerais --}}
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Informações Gerais</h2>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $custBadge['badge'] }}">
                        <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $custBadge['dot'] }}"></span>
                        {{ $custBadge['label'] }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $docBadge['badge'] }}">
                        <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $docBadge['dot'] }}"></span>
                        Doc: {{ __($docBadge['label']) }}
                    </span>
                </div>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                @php
                    $fields = [
                        ['label' => 'Nome completo', 'value' => $customer->name],
                        ['label' => 'Username',      'value' => $customer->username],
                        ['label' => 'Email',         'value' => $customer->email],
                        ['label' => 'Telefone',      'value' => $customer->phone],
                        ['label' => 'Tipo',          'value' => ucfirst($customer->type ?? '—')],
                        ['label' => 'Idioma',        'value' => strtoupper($customer->language ?? '—')],
                        ['label' => 'Email verificado', 'value' => $customer->email_verified_at?->format('d/m/Y H:i') ?? 'Não verificado'],
                        ['label' => 'Cadastro',      'value' => $customer->created_at?->format('d/m/Y H:i')],
                        ['label' => 'Telefone verificado', 'value' => $customer->phoneVerifications->last()?->verified_at?->format('d/m/Y H:i') ?? 'Não verificado'],
                    ];
                @endphp
                @foreach ($fields as $f)
                <div>
                    <p class="text-xs text-slate-400 font-medium mb-0.5">{{ $f['label'] }}</p>
                    <p class="text-sm text-slate-800 font-medium">{{ $f['value'] ?? '—' }}</p>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Documentos --}}
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Documentos</h2>
                <span class="text-xs text-slate-500 bg-slate-100 px-2 py-1 rounded-full font-medium">{{ $customer->documents->count() }}</span>
            </div>
            @forelse ($customer->documents as $doc)
            <div class="px-5 py-4 border-b border-slate-50 last:border-0">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-4">
                    @php
                        $docFields = [
                            ['label' => 'Tipo', 'value' => $doc->document_type],
                            ['label' => 'Número', 'value' => $doc->document_number],
                            ['label' => 'Nome', 'value' => $doc->full_name],
                            ['label' => 'Nascimento', 'value' => $doc->date_of_birth],
                            ['label' => 'Validade', 'value' => $doc->expiration_date],
                            ['label' => 'Emissão', 'value' => $doc->date_of_issue],
                            ['label' => 'País Emissor', 'value' => $doc->issuing_state_name],
                            ['label' => 'Nacionalidade', 'value' => $doc->nationality],
                        ];
                    @endphp
                    @foreach ($docFields as $f)
                    @if ($f['value'])
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">{{ $f['label'] }}</p>
                        <p class="text-sm text-slate-800 font-medium">{{ $f['value'] }}</p>
                    </div>
                    @endif
                    @endforeach
                </div>

                {{-- Imagens do documento --}}
                @php
                    $mediaFields = [
                        ['label' => 'Frente',      'field' => 'front_image'],
                        ['label' => 'Verso',       'field' => 'back_image'],
                        ['label' => 'Retrato',     'field' => 'portrait_image'],
                        ['label' => 'Frente (Full)','field' => 'full_front_image'],
                        ['label' => 'Verso (Full)', 'field' => 'full_back_image'],
                    ];
                    $videoFields = [
                        ['label' => 'Vídeo Frente', 'field' => 'front_video'],
                        ['label' => 'Vídeo Verso',  'field' => 'back_video'],
                    ];
                @endphp
                @php $hasMedia = collect($mediaFields)->some(fn($m) => !empty($doc->{$m['field']})); @endphp
                @if ($hasMedia)
                <div class="mt-3">
                    <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-2">Imagens</p>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($mediaFields as $m)
                        @if (!empty($doc->{$m['field']}))
                        <div class="flex flex-col items-center gap-1">
                            <a href="{{ Storage::url($doc->{$m['field']}) }}" target="_blank">
                                <img src="{{ Storage::url($doc->{$m['field']}) }}"
                                     alt="{{ $m['label'] }}"
                                     class="w-24 h-16 object-cover rounded-lg border border-slate-200 shadow-sm hover:opacity-90 transition">
                            </a>
                            <span class="text-xs text-slate-500">{{ $m['label'] }}</span>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif

                @php $hasVideo = collect($videoFields)->some(fn($v) => !empty($doc->{$v['field']})); @endphp
                @if ($hasVideo)
                <div class="mt-3">
                    <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-2">Vídeos</p>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($videoFields as $v)
                        @if (!empty($doc->{$v['field']}))
                        <div class="flex flex-col items-center gap-1">
                            <video src="{{ Storage::url($doc->{$v['field']}) }}"
                                   controls
                                   class="w-40 rounded-lg border border-slate-200 shadow-sm"></video>
                            <span class="text-xs text-slate-500">{{ $v['label'] }}</span>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Scores --}}
                @if ($doc->face_match_score || $doc->liveness_score)
                <div class="mt-3 flex flex-wrap gap-3">
                    @if ($doc->face_match_score)
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-slate-50 rounded-lg border border-slate-200 text-xs">
                        <span class="text-slate-500">Face Match</span>
                        <span class="font-semibold text-slate-900">{{ number_format($doc->face_match_score * 100, 1) }}%</span>
                    </div>
                    @endif
                    @if ($doc->liveness_score)
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-slate-50 rounded-lg border border-slate-200 text-xs">
                        <span class="text-slate-500">Liveness</span>
                        <span class="font-semibold text-slate-900">{{ number_format($doc->liveness_score * 100, 1) }}%</span>
                    </div>
                    @endif
                </div>
                @endif
            </div>
            @empty
            <div class="px-5 py-10 text-center">
                <p class="text-sm text-slate-400">Nenhum documento cadastrado.</p>
            </div>
            @endforelse
        </div>

        {{-- Transações --}}
        @if ($customer->transactions->isNotEmpty())
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Últimas Transações</h2>
                <span class="text-xs text-slate-500 bg-slate-100 px-2 py-1 rounded-full font-medium">{{ $customer->transactions->count() }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-50/60 border-b border-slate-100">
                            <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-5 py-3">ID</th>
                            <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Valor</th>
                            <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Status</th>
                            <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($customer->transactions->take(10) as $tx)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-5 py-3 text-xs font-mono text-slate-500">{{ $tx->id }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ $tx->amount ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $tx->status ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $tx->created_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>

    {{-- ==================== RIGHT COLUMN ==================== --}}
    <div class="flex flex-col gap-6">

        {{-- Conta Stripe --}}
        @if ($customer->stripeAccount)
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-slate-900 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                Conta Stripe
            </h2>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Customer ID</span>
                    <span class="font-mono text-xs text-slate-700 bg-slate-50 px-2 py-0.5 rounded">{{ $customer->stripeAccount->stripe_customer_id ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Account ID</span>
                    <span class="font-mono text-xs text-slate-700 bg-slate-50 px-2 py-0.5 rounded">{{ $customer->stripeAccount->stripe_account_id ?? '—' }}</span>
                </div>
            </div>
        </div>
        @endif

        {{-- Conta Wallester --}}
        @if ($customer->wallesterAccounts)
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-slate-900 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Conta Wallester
            </h2>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Account ID</span>
                    <span class="font-mono text-xs text-slate-700 bg-slate-50 px-2 py-0.5 rounded">{{ $customer->wallesterAccounts->wallester_account_id ?? '—' }}</span>
                </div>
            </div>
        </div>
        @endif

        {{-- Responsáveis (corporate) --}}
        @if ($customer->responsibles->isNotEmpty())
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Responsáveis</h2>
                <span class="text-xs text-slate-500 bg-slate-100 px-2 py-1 rounded-full font-medium">{{ $customer->responsibles->count() }}</span>
            </div>
            <div class="divide-y divide-slate-50">
                @foreach ($customer->responsibles as $resp)
                <div class="px-5 py-3 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-violet-400 to-indigo-500
                                flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                        {{ strtoupper(substr($resp->name ?? 'R', 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900 truncate">{{ $resp->name }}</p>
                        <p class="text-xs text-slate-400 truncate">{{ $resp->email }}</p>
                    </div>
                    <a href="{{ route('dashboard.customers.show', $resp) }}"
                       class="ml-auto text-xs text-indigo-600 hover:underline flex-shrink-0">Ver</a>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Timestamps --}}
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-slate-900 mb-4">Histórico</h2>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Criado em</span>
                    <span class="text-slate-700 font-medium">{{ $customer->created_at?->format('d/m/Y H:i') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Atualizado em</span>
                    <span class="text-slate-700 font-medium">{{ $customer->updated_at?->format('d/m/Y H:i') }}</span>
                </div>
                @if ($customer->email_verified_at)
                <div class="flex justify-between">
                    <span class="text-slate-500">Email verificado</span>
                    <span class="text-emerald-600 font-medium">{{ $customer->email_verified_at->format('d/m/Y H:i') }}</span>
                </div>
                @endif
            </div>
        </div>

    </div>
</div>

@endsection
