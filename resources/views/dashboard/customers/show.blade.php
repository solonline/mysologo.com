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
        @php
            $avatarUrl = \App\Domains\Kyc\Support\CustomerKycMedia::url($customer->avatar);
        @endphp
        @if ($avatarUrl)
        <img src="{{ $avatarUrl }}" alt="{{ $customer->name }}"
             class="w-12 h-12 rounded-2xl object-cover border border-slate-200 shadow-md">
        @else
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-400 to-violet-500
                    flex items-center justify-center text-white text-base font-bold shadow-md shadow-indigo-500/20">
            {{ strtoupper(substr($customer->name ?? 'C', 0, 2)) }}
        </div>
        @endif
        <div>
            <h1 class="text-xl font-bold text-slate-900">{{ $customer->name }}</h1>
            <p class="text-sm text-slate-500">{{ $customer->email }}</p>
        </div>
    </div>
    @can('customers.create')
    <a href="{{ route('dashboard.customers.create') }}"
       class="ml-auto inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Novo cliente
    </a>
    @endcan
</div>
@endsection

@section('content')

@php
    $resolveMediaUrl = fn (?string $path): ?string => \App\Domains\Kyc\Support\CustomerKycMedia::url($path);

    $formatDateTime = function ($value, string $fallback = '—'): string {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y H:i');
        }

        return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y H:i');
    };

    $custVal = $customer->status?->value ?? $customer->status ?? '';
    $wallesterStatus = $customer->wallesterAccount?->status;
    $wallesterFailed = $wallesterStatus instanceof \App\Domains\Onboarding\Enums\IntegrationAccountStatus
        ? $wallesterStatus === \App\Domains\Onboarding\Enums\IntegrationAccountStatus::FAILED
        : strtoupper((string) $wallesterStatus) === 'FAILED';
    $wallesterActive = $wallesterStatus instanceof \App\Domains\Onboarding\Enums\IntegrationAccountStatus
        ? $wallesterStatus === \App\Domains\Onboarding\Enums\IntegrationAccountStatus::ACTIVE
        : strtoupper((string) $wallesterStatus) === 'ACTIVE';
    $canRetryProvisioning = ($customer->status === \App\Domains\Onboarding\Enums\CustomerLifecycleStatus::PROVISIONING)
        && ($wallesterFailed || $customer->wallesterAccount === null);
    $pendingBalanceAdjustment = $pendingBalanceAdjustment ?? null;
    $pendingBalanceAdjustmentPayload = $pendingBalanceAdjustment
        ? [
            'id' => $pendingBalanceAdjustment->id,
            'direction' => $pendingBalanceAdjustment->direction->value,
            'direction_label' => $pendingBalanceAdjustment->direction->label(),
            'amount_cents' => $pendingBalanceAdjustment->amount_cents,
            'amount_formatted' => number_format($pendingBalanceAdjustment->amount_cents / 100, 2, ',', '.'),
            'currency' => $pendingBalanceAdjustment->currency,
            'description' => $pendingBalanceAdjustment->description,
            'status' => $pendingBalanceAdjustment->status->value,
        ]
        : null;
    $stripeFundingStatus = $customer->stripeAccount?->funding_status;
    $stripeFundingActive = $stripeFundingStatus === \App\Domains\Stripe\Enums\StripeFundingStatus::ACTIVE;
    $stripeFundingFailed = $stripeFundingStatus === \App\Domains\Stripe\Enums\StripeFundingStatus::FAILED;
    $stripeFundingPending = $stripeFundingStatus === \App\Domains\Stripe\Enums\StripeFundingStatus::PENDING;
    $canRetryStripeFunding = $customer->stripeAccount !== null
        && ($stripeFundingStatus?->canRetry() ?? false);
    $stripeFundingBadge = match ($stripeFundingStatus) {
        \App\Domains\Stripe\Enums\StripeFundingStatus::ACTIVE => ['badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'label' => 'Activo'],
        \App\Domains\Stripe\Enums\StripeFundingStatus::FAILED => ['badge' => 'bg-red-50 text-red-700 border-red-200', 'label' => 'Falhou'],
        \App\Domains\Stripe\Enums\StripeFundingStatus::PENDING => ['badge' => 'bg-amber-50 text-amber-700 border-amber-200', 'label' => 'Pendente'],
        default => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200', 'label' => '—'],
    };
    $custBadge = match($customer->status) {
        \App\Domains\Onboarding\Enums\CustomerLifecycleStatus::ACTIVE => ['badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Ativo'],
        \App\Domains\Onboarding\Enums\CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION,
        \App\Domains\Onboarding\Enums\CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION,
        \App\Domains\Onboarding\Enums\CustomerLifecycleStatus::PENDING_IDENTITY_VERIFICATION => ['badge' => 'bg-amber-50 text-amber-700 border-amber-200', 'dot' => 'bg-amber-500', 'label' => $customer->status?->label() ?? 'Pendente'],
        \App\Domains\Onboarding\Enums\CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        \App\Domains\Onboarding\Enums\CustomerLifecycleStatus::PROVISIONING,
        \App\Domains\Onboarding\Enums\CustomerLifecycleStatus::PENDING_EBURY_ONBOARDING,
        \App\Domains\Onboarding\Enums\CustomerLifecycleStatus::EBURY_UNDER_REVIEW => ['badge' => 'bg-sky-50 text-sky-700 border-sky-200', 'dot' => 'bg-sky-500', 'label' => $customer->status?->label() ?? 'Em análise'],
        \App\Domains\Onboarding\Enums\CustomerLifecycleStatus::IDENTITY_REJECTED,
        \App\Domains\Onboarding\Enums\CustomerLifecycleStatus::EBURY_REJECTED,
        \App\Domains\Onboarding\Enums\CustomerLifecycleStatus::BLOCKED => ['badge' => 'bg-red-50 text-red-700 border-red-200', 'dot' => 'bg-red-500', 'label' => $customer->status?->label() ?? 'Bloqueado'],
        \App\Domains\Onboarding\Enums\CustomerLifecycleStatus::SUSPENDED => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200', 'dot' => 'bg-slate-400', 'label' => 'Suspenso'],
        default => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200', 'dot' => 'bg-slate-400', 'label' => $customer->status?->label() ?? ($custVal ?: '—')],
    };
    $docVal = $customer->doc_status?->value ?? $customer->doc_status ?? '';
    $docBadge = match(strtoupper($docVal)) {
        'APPROVED'  => ['badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Aprovado'],
        'PENDING'   => ['badge' => 'bg-amber-50 text-amber-700 border-amber-200',       'dot' => 'bg-amber-500',   'label' => 'Pendente'],
        'ANALYSING' => ['badge' => 'bg-sky-50 text-sky-700 border-sky-200',             'dot' => 'bg-sky-500',     'label' => 'Em Análise'],
        'DECLINED'  => ['badge' => 'bg-red-50 text-red-700 border-red-200',             'dot' => 'bg-red-500',     'label' => 'Recusado'],
        default     => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200',      'dot' => 'bg-slate-400',   'label' => $docVal ?: '—'],
    };
    $acceptedCurrencies = collect(app(\App\Domains\Backoffice\Queries\GetAcceptedCurrenciesQuery::class)->execute())
        ->push($customer->currency)
        ->filter()
        ->map(fn ($currency) => strtoupper((string) $currency))
        ->unique()
        ->values();
    $remainingFeeCurrencies = function ($entity, $allowed = null) use ($acceptedCurrencies): \Illuminate\Support\Collection {
        $pool = collect($allowed ?? [])->filter()->map(fn ($currency) => strtoupper((string) $currency));
        if ($pool->isEmpty()) {
            $pool = $acceptedCurrencies;
        }
        if ($pool->isEmpty()) {
            $pool = collect(['EUR']);
        }

        $used = $entity->paymentMethodFees
            ->pluck('currency')
            ->map(fn ($currency) => strtoupper((string) $currency));

        return $pool->diff($used)->values();
    };
@endphp

@if ($wallesterFailed)
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-5 py-4 rounded-2xl border border-red-200 bg-red-50">
    <div>
        <p class="text-sm font-semibold text-red-800">Falha ao provisionar Wallester</p>
        <p class="text-xs text-red-700 mt-1">{{ $customer->wallesterAccount->status_reason ?? $customer->status_reason ?? 'Erro desconhecido.' }}</p>
    </div>
    @can('customers.edit')
    @if ($canRetryProvisioning)
    <form method="POST" action="{{ route('dashboard.customers.retry-provisioning', $customer) }}">
        @csrf
        <button type="submit" class="btn-secondary btn-sm whitespace-nowrap">Repetir provisioning</button>
    </form>
    @endif
    @endcan
</div>
@endif

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    {{-- ==================== LEFT COLUMN ==================== --}}
    <div class="xl:col-span-2 flex flex-col gap-6">

        {{-- Informações Gerais --}}
        <div x-data="{ editing: {{ $errors->any() ? 'true' : 'false' }} }"
             class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">

            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Informações Gerais</h2>
                <div class="flex items-center gap-2">
                    {{-- Badges (view mode) --}}
                    <template x-if="!editing">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $custBadge['badge'] }}">
                                <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $custBadge['dot'] }}"></span>
                                {{ $custBadge['label'] }}
                            </span>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $docBadge['badge'] }}">
                                <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $docBadge['dot'] }}"></span>
                                Doc: {{ $docBadge['label'] }}
                            </span>
                        </div>
                    </template>

                    @can('customers.edit')
                    <button x-show="!editing" @click="editing = true"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Editar
                    </button>
                    @endcan
                </div>
            </div>

            {{-- VIEW MODE --}}
            <div x-show="!editing" class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                @php
                    $fields = [
                        ['label' => 'Nome completo',       'value' => $customer->name],
                        ['label' => 'Username',            'value' => $customer->username],
                        ['label' => 'Email',               'value' => $customer->email],
                        ['label' => 'Telefone',            'value' => $customer->phone],
                        ['label' => 'Tipo',                'value' => ucfirst($customer->type ?? '—')],
                        ['label' => 'Idioma',              'value' => strtoupper($customer->language ?? '—')],
                        ['label' => 'Moeda',               'value' => strtoupper($customer->currency ?? '—')],
                        ['label' => 'Email verificado',    'value' => $customer->email_verified_at ? $formatDateTime($customer->email_verified_at, 'Não verificado') : 'Não verificado'],
                        ['label' => 'Telefone verificado', 'value' => $currentPhoneVerified ? $formatDateTime($currentPhoneVerifiedAt, 'Não verificado') : 'Não verificado'],
                        ['label' => 'Cadastro',            'value' => $formatDateTime($customer->created_at)],
                    ];

                    if ($customer->referredBy) {
                        $fields[] = [
                            'label' => 'Indicado por',
                            'value' => $customer->referredBy->username.' (#'.$customer->referredBy->id.')',
                            'link' => route('dashboard.customers.show', $customer->referredBy),
                        ];
                    }

                    if (filled($customer->status_reason)) {
                        $fields[] = ['label' => 'Motivo do estado', 'value' => $customer->status_reason];
                    }
                @endphp
                @foreach ($fields as $f)
                <div>
                    <p class="text-xs text-slate-400 font-medium mb-0.5">{{ $f['label'] }}</p>
                    @if (!empty($f['link']))
                    <p class="text-sm text-slate-800 font-medium">
                        <a href="{{ $f['link'] }}" class="text-indigo-600 hover:text-indigo-700 hover:underline">{{ $f['value'] ?? '—' }}</a>
                    </p>
                    @else
                    <p class="text-sm text-slate-800 font-medium">{{ $f['value'] ?? '—' }}</p>
                    @endif
                </div>
                @endforeach
            </div>

            {{-- EDIT MODE --}}
            <div x-show="editing" x-cloak>
                <form method="POST" action="{{ route('dashboard.customers.update', $customer) }}">
                    @csrf
                    @method('PUT')

                    <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Nome completo</label>
                            <input type="text" name="name" value="{{ old('name', $customer->name) }}" required
                                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('name') border-red-400 @enderror">
                            @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Email</label>
                            <input type="email" name="email" value="{{ old('email', $customer->email) }}" required
                                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('email') border-red-400 @enderror">
                            @error('email')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Moeda</label>
                            <input type="text" name="currency" value="{{ old('currency', $customer->currency) }}" maxlength="3"
                                   placeholder="EUR"
                                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 uppercase @error('currency') border-red-400 @enderror">
                            @error('currency')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Idioma</label>
                            <input type="text" name="language" value="{{ old('language', $customer->language) }}" maxlength="5"
                                   placeholder="pt"
                                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('language') border-red-400 @enderror">
                            @error('language')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Status</label>
                            <select name="status"
                                    class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('status') border-red-400 @enderror">
                                @foreach ($customerStatuses as $cs)
                                <option value="{{ $cs->value }}" {{ old('status', $custVal) === $cs->value ? 'selected' : '' }}>
                                    {{ $cs->label() }}
                                </option>
                                @endforeach
                            </select>
                            @error('status')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Status do Documento</label>
                            <select name="doc_status"
                                    class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('doc_status') border-red-400 @enderror">
                                <option value="">— Não definido —</option>
                                @foreach ($documentStatuses as $ds)
                                <option value="{{ $ds->value }}" {{ strtoupper((old('doc_status', $docVal) ?? $docVal)) == $ds->value ? 'selected' : '' }}>
                                    {{ $ds->value }}
                                </option>
                                @endforeach
                            </select>
                            @error('doc_status')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        {{-- Read-only fields --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Username</label>
                            <p class="px-3 py-2 text-sm text-slate-500 bg-slate-100 border border-slate-200 rounded-xl">{{ $customer->username }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Tipo</label>
                            <p class="px-3 py-2 text-sm text-slate-500 bg-slate-100 border border-slate-200 rounded-xl">{{ ucfirst($customer->type ?? '—') }}</p>
                        </div>

                    </div>

                    <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button type="button" @click="editing = false"
                                class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-500/25">
                            Guardar alterações
                        </button>
                    </div>
                </form>
            </div>

        </div>{{-- fim Informações Gerais --}}

        {{-- Telefone --}}
        <div x-data="{ sectionOpen: false }"
             class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <button type="button"
                    @click="sectionOpen = !sectionOpen"
                    class="w-full px-5 py-4 flex items-center justify-between gap-3 text-left hover:bg-slate-50/80 transition-colors"
                    :class="sectionOpen ? 'border-b border-slate-100' : ''">
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-slate-900">Telefone</h2>
                    <p class="text-xs text-slate-500 mt-0.5 truncate">
                        {{ $customer->phone ?: '—' }}
                        · {{ $currentPhoneVerified ? 'Confirmado' : 'Não confirmado' }}
                    </p>
                </div>
                <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform" :class="sectionOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="sectionOpen" x-cloak class="p-5 space-y-5">
                <p class="text-xs text-slate-500">
                    O telefone só é alterado após confirmação do código SMS enviado para o novo número.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">Número actual</p>
                        <p class="text-sm text-slate-800 font-medium">{{ $customer->phone ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">Estado</p>
                        <p class="text-sm text-slate-800 font-medium">
                            {{ $currentPhoneVerified ? 'Confirmado' : 'Não confirmado' }}
                        </p>
                    </div>
                </div>

                @can('customers.edit')
                @if (! $currentPhoneVerified && $customer->phone && ! $pendingPhoneChange)
                <div class="border-t border-slate-100 pt-5 space-y-4">
                    @if ($pendingCurrentPhoneVerification)
                    <form method="POST" action="{{ route('dashboard.customers.phone.verify.confirm', $customer) }}" class="flex flex-col sm:flex-row gap-3 items-end">
                        @csrf
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Código SMS</label>
                            <input type="text" name="code" value="{{ old('code') }}" required maxlength="10" autocomplete="one-time-code"
                                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('code') border-red-400 @enderror">
                            @error('code')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 transition-colors shadow-sm shadow-emerald-500/25 whitespace-nowrap">
                            Confirmar telefone
                        </button>
                    </form>
                    <form method="POST" action="{{ route('dashboard.customers.phone.verify.send', $customer) }}">
                        @csrf
                        <button type="submit"
                                class="px-3 py-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors">
                            Reenviar SMS
                        </button>
                    </form>
                    @else
                    <form method="POST" action="{{ route('dashboard.customers.phone.verify.send', $customer) }}">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-500/25">
                            Enviar SMS
                        </button>
                    </form>
                    @endif
                </div>
                @endif
                @endcan

                @can('customers.edit')
                @if ($pendingPhoneChange)
                <div class="border-t border-slate-100 pt-5 space-y-4">
                    <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3">
                        <p class="text-sm text-amber-900">
                            Alteração pendente para <span class="font-semibold">{{ $pendingPhoneChange }}</span>.
                            Introduza o código SMS ou altere o número de destino abaixo.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('dashboard.customers.phone.confirm', $customer) }}" class="flex flex-col sm:flex-row gap-3 items-end">
                        @csrf
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Código SMS</label>
                            <input type="text" name="code" value="{{ old('code') }}" required maxlength="10" autocomplete="one-time-code"
                                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('code') border-red-400 @enderror">
                            @error('code')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 transition-colors shadow-sm shadow-emerald-500/25 whitespace-nowrap">
                            Confirmar telefone
                        </button>
                    </form>

                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('dashboard.customers.phone.resend', $customer) }}">
                            @csrf
                            <button type="submit"
                                    class="px-3 py-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors">
                                Reenviar SMS
                            </button>
                        </form>
                        <form method="POST" action="{{ route('dashboard.customers.phone.cancel', $customer) }}"
                              onsubmit="return confirm('Cancelar a alteração pendente?')">
                            @csrf
                            <button type="submit"
                                    class="px-3 py-1.5 text-xs font-semibold text-slate-600 bg-slate-50 border border-slate-200 rounded-lg hover:bg-slate-100 transition-colors">
                                Cancelar pendência
                            </button>
                        </form>
                    </div>
                </div>
                @endif

                <form method="POST" action="{{ route('dashboard.customers.phone.request', $customer) }}" class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-3 items-end {{ $pendingPhoneChange ? 'border-t border-slate-100 pt-5' : '' }}">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                            {{ $pendingPhoneChange ? 'Alterar número de destino' : 'Novo telefone' }}
                        </label>
                        <input type="text" name="phone" value="{{ old('phone', $pendingPhoneChange) }}" required
                               inputmode="numeric" pattern="[0-9]+" maxlength="20" placeholder="351912345678"
                               class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('phone') border-red-400 @enderror">
                        @error('phone')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-500/25">
                        {{ $pendingPhoneChange ? 'Enviar para novo número' : 'Pedir código de alteração' }}
                    </button>
                </form>
                @endcan
            </div>{{-- fim sectionOpen --}}
        </div>

        {{-- Documentos --}}
        <div x-data="{ sectionOpen: false }"
             class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <button type="button"
                    @click="sectionOpen = !sectionOpen"
                    class="w-full px-5 py-4 flex items-center justify-between gap-3 text-left hover:bg-slate-50/80 transition-colors"
                    :class="sectionOpen ? 'border-b border-slate-100' : ''">
                <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                    Documentos KYC
                    <span class="text-xs text-slate-500 bg-slate-100 px-2 py-1 rounded-full font-medium">{{ $customer->documents->count() }}</span>
                </h2>
                <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform" :class="sectionOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="sectionOpen" x-cloak>
            @forelse ($customer->documents as $doc)
            @php
                $extraFields = is_array($doc->extra_fields) ? $doc->extra_fields : [];
                $warnings = $extraFields['warnings'] ?? [];
                $verificationStatus = $extraFields['status'] ?? null;
                $documentSubtype = $extraFields['document_subtype'] ?? null;
                $verificationBadge = match (strtolower(str_replace(' ', '', (string) $verificationStatus))) {
                    'approved' => ['badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'label' => 'Aprovado'],
                    'declined' => ['badge' => 'bg-red-50 text-red-700 border-red-200', 'label' => 'Recusado'],
                    'inreview' => ['badge' => 'bg-sky-50 text-sky-700 border-sky-200', 'label' => 'Em análise'],
                    default => null,
                };
            @endphp
            <div x-data="{ showJson: false }" class="px-5 py-4 border-b border-slate-50 last:border-0">

                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <p class="text-sm font-semibold text-slate-900 font-mono">{{ $doc->document_number }}</p>
                    @if ($verificationBadge)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $verificationBadge['badge'] }}">
                        {{ $verificationBadge['label'] }}
                    </span>
                    @endif
                    @if ($documentSubtype)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                        {{ $documentSubtype }}
                    </span>
                    @endif
                </div>

                {{-- Motivos de recusa / avisos Didit --}}
                @if (!empty($warnings))
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50/60 overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-red-100 bg-red-50">
                        <p class="text-xs font-semibold uppercase tracking-wider text-red-800">
                            Motivos da verificação ({{ count($warnings) }})
                        </p>
                    </div>
                    <div class="divide-y divide-red-100">
                        @foreach ($warnings as $warning)
                        @php
                            $logType = strtolower($warning['log_type'] ?? 'warning');
                            $warningStyle = match ($logType) {
                                'error' => ['icon' => 'bg-red-500', 'title' => 'text-red-900', 'body' => 'text-red-700'],
                                'information' => ['icon' => 'bg-slate-400', 'title' => 'text-slate-800', 'body' => 'text-slate-600'],
                                default => ['icon' => 'bg-amber-500', 'title' => 'text-amber-900', 'body' => 'text-amber-800'],
                            };
                        @endphp
                        <div class="px-4 py-3 flex gap-3">
                            <span class="mt-1.5 w-2 h-2 rounded-full flex-shrink-0 {{ $warningStyle['icon'] }}"></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <p class="text-sm font-semibold {{ $warningStyle['title'] }}">
                                        {{ $warning['short_description'] ?? 'Aviso' }}
                                    </p>
                                    @if (!empty($warning['risk']))
                                    <span class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-white/80 text-slate-600 border border-slate-200">
                                        {{ $warning['risk'] }}
                                    </span>
                                    @endif
                                </div>
                                @if (!empty($warning['long_description']))
                                <p class="text-xs leading-relaxed {{ $warningStyle['body'] }}">{{ $warning['long_description'] }}</p>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Campos de identidade --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-3 mb-4">
                    @php
                        $docFields = [
                            ['label' => 'Tipo',            'value' => $doc->document_type],
                            ['label' => 'Número',          'value' => $doc->document_number],
                            ['label' => 'Número Pessoal',  'value' => $doc->personal_number],
                            ['label' => 'Nome completo',   'value' => $doc->full_name],
                            ['label' => 'Primeiro nome',   'value' => $doc->first_name],
                            ['label' => 'Último nome',     'value' => $doc->last_name],
                            ['label' => 'Nascimento',      'value' => $doc->date_of_birth],
                            ['label' => 'Idade',           'value' => $doc->age],
                            ['label' => 'Género',          'value' => $doc->gender],
                            ['label' => 'Estado civil',    'value' => $doc->marital_status],
                            ['label' => 'Naturalidade',    'value' => $doc->place_of_birth],
                            ['label' => 'Nacionalidade',   'value' => $doc->nationality],
                            ['label' => 'País Emissor',    'value' => $doc->issuing_state_name],
                            ['label' => 'Emissão',         'value' => $doc->date_of_issue],
                            ['label' => 'Validade',        'value' => $doc->expiration_date],
                            ['label' => 'Morada',          'value' => $doc->formatted_address ?? $doc->address],
                        ];
                    @endphp
                    @foreach ($docFields as $f)
                    @if (!is_null($f['value']) && $f['value'] !== '')
                    <div>
                        <p class="text-xs text-slate-400 font-medium mb-0.5">{{ $f['label'] }}</p>
                        <p class="text-sm text-slate-800 font-medium">{{ $f['value'] }}</p>
                    </div>
                    @endif
                    @endforeach
                </div>

                {{-- Quality scores OCR --}}
                @php
                    $qualityScores = array_filter([
                        'Qualidade Frente' => $doc->front_image_quality_score['overall_score'] ?? null,
                        'Qualidade Verso' => $doc->back_image_quality_score['overall_score'] ?? null,
                    ], fn ($score) => $score !== null);
                @endphp
                @if (!empty($qualityScores))
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach ($qualityScores as $label => $score)
                    @php
                        $pct = round((float) $score, 1);
                        $color = $pct >= 80 ? 'text-emerald-700 bg-emerald-50 border-emerald-200'
                               : ($pct >= 60 ? 'text-amber-700 bg-amber-50 border-amber-200'
                               : 'text-red-700 bg-red-50 border-red-200');
                    @endphp
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs {{ $color }}">
                        <span>{{ $label }}</span>
                        <span class="font-bold">{{ $pct }}%</span>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Scores --}}
                @php
                    $scores = array_filter([
                        'Face Match'         => $doc->face_match_score,
                        'Liveness'           => $doc->liveness_score,
                        'Face Match (Frente)' => $doc->front_image_camera_front_face_match_score,
                        'Face Match (Verso)'  => $doc->back_image_camera_front_face_match_score,
                    ]);
                @endphp
                @if (!empty($scores))
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach ($scores as $label => $score)
                    @php
                        $pct = round($score * 100, 1);
                        $color = $pct >= 80 ? 'text-emerald-700 bg-emerald-50 border-emerald-200'
                               : ($pct >= 60 ? 'text-amber-700 bg-amber-50 border-amber-200'
                               : 'text-red-700 bg-red-50 border-red-200');
                    @endphp
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs {{ $color }}">
                        <span>{{ $label }}</span>
                        <span class="font-bold">{{ $pct }}%</span>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Imagens --}}
                @php
                    $mediaFields = [
                        ['label' => 'Frente',             'field' => 'front_image'],
                        ['label' => 'Verso',              'field' => 'back_image'],
                        ['label' => 'Retrato',            'field' => 'portrait_image'],
                        ['label' => 'Frente (Full)',      'field' => 'full_front_image'],
                        ['label' => 'Verso (Full)',       'field' => 'full_back_image'],
                        ['label' => 'Frente (Câmera F)', 'field' => 'front_image_camera_front'],
                        ['label' => 'Verso (Câmera F)',  'field' => 'back_image_camera_front'],
                    ];
                    $hasMedia = collect($mediaFields)->some(fn($m) => !empty($doc->{$m['field']}));
                    $videoFields = [
                        ['label' => 'Vídeo Frente', 'field' => 'front_video'],
                        ['label' => 'Vídeo Verso',  'field' => 'back_video'],
                    ];
                    $hasVideo = collect($videoFields)->some(fn($v) => !empty($doc->{$v['field']}));
                @endphp

                @if ($hasMedia)
                <div class="mb-3">
                    <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-2">Imagens</p>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($mediaFields as $m)
                        @php $mediaUrl = $resolveMediaUrl($doc->{$m['field']}); @endphp
                        @if ($mediaUrl)
                        <div class="flex flex-col items-center gap-1">
                            <a href="{{ $mediaUrl }}" target="_blank">
                                <img src="{{ $mediaUrl }}"
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

                @if ($hasVideo)
                <div class="mb-3">
                    <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-2">Vídeos</p>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($videoFields as $v)
                        @php $videoUrl = $resolveMediaUrl($doc->{$v['field']}); @endphp
                        @if ($videoUrl)
                        <div class="flex flex-col items-center gap-1">
                            <video src="{{ $videoUrl }}"
                                   controls
                                   class="w-40 rounded-lg border border-slate-200 shadow-sm"></video>
                            <span class="text-xs text-slate-500">{{ $v['label'] }}</span>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Análises JSON (collapsible) --}}
                @php
                    $extraFieldsForJson = collect($extraFields)
                        ->except(['warnings', 'status', 'document_subtype', 'node_id', 'matches', 'mrz'])
                        ->filter(fn ($value) => $value !== null && $value !== [] && $value !== '')
                        ->all();

                    $jsonSections = array_filter([
                        'Endereço Estruturado' => $doc->parsed_address,
                        'Análise de IP'        => $doc->ip_analyses,
                        'Verificações NFC'     => $doc->nfc_verifications,
                        'Campos Extras'        => !empty($extraFieldsForJson) ? $extraFieldsForJson : null,
                    ]);
                @endphp
                @if (!empty($jsonSections))
                <div class="mt-3">
                    <button @click="showJson = !showJson"
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-slate-800 transition-colors">
                        <svg class="w-3.5 h-3.5 transition-transform" :class="showJson ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span x-text="showJson ? 'Ocultar dados de análise' : 'Ver dados de análise'"></span>
                    </button>
                    <div x-show="showJson" x-cloak class="mt-3 space-y-3">
                        @foreach ($jsonSections as $label => $data)
                        <div>
                            <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">{{ $label }}</p>
                            <pre class="text-xs text-slate-700 bg-slate-50 border border-slate-200 rounded-xl p-3 overflow-x-auto">{{ json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>
            @empty
            <div class="px-5 py-10 text-center">
                <p class="text-sm text-slate-400">Nenhum documento cadastrado.</p>
            </div>
            @endforelse
            </div>{{-- fim sectionOpen --}}
        </div>

        {{-- TPAs --}}
        <div x-data="{
                 sectionOpen: false,
                 showFeeModal: false,
                 activeTpaId: null,
                 expandedTpa: null,
             }">
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">

            <button type="button"
                    @click="sectionOpen = !sectionOpen"
                    class="w-full px-5 py-4 flex items-center justify-between gap-3 text-left hover:bg-slate-50/80 transition-colors"
                    :class="sectionOpen ? 'border-b border-slate-100' : ''">
                <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    TPAs
                    <span class="text-xs text-slate-500 bg-slate-100 px-2 py-1 rounded-full font-medium">{{ $customer->tpas->count() }}</span>
                </h2>
                <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform" :class="sectionOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="sectionOpen" x-cloak>
            @forelse ($customer->tpas as $tpa)
            @php
                $tpaBadge = match($tpa->status) {
                    'active'   => ['badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Ativo'],
                    'inactive' => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200',      'dot' => 'bg-slate-400',   'label' => 'Inativo'],
                    'blocked'  => ['badge' => 'bg-red-50 text-red-700 border-red-200',             'dot' => 'bg-red-500',     'label' => 'Bloqueado'],
                    default    => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200',      'dot' => 'bg-slate-400',   'label' => $tpa->status],
                };
            @endphp
            <div class="border-b border-slate-50 last:border-0">
                <div class="px-5 py-3 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-900 font-mono">{{ $tpa->serial }}</p>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $tpaBadge['badge'] }} mt-0.5">
                            <span class="w-1.5 h-1.5 rounded-full mr-1 {{ $tpaBadge['dot'] }}"></span>
                            {{ $tpaBadge['label'] }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button @click="expandedTpa = (expandedTpa === {{ $tpa->id }}) ? null : {{ $tpa->id }}"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Taxas
                            <span class="bg-indigo-200 text-indigo-700 rounded-full px-1.5 py-0.5 text-xs leading-none">{{ $tpa->paymentMethodFees->count() }}</span>
                        </button>
                        <form method="POST" action="{{ route('dashboard.customers.tpas.destroy', [$customer, $tpa]) }}"
                              onsubmit="return confirm('Remover este TPA?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>

                @php $tpaRemainingCurrencies = $remainingFeeCurrencies($tpa); @endphp
                <div x-show="expandedTpa === {{ $tpa->id }}" x-cloak class="px-5 pb-4 bg-slate-50/60 border-t border-slate-100">
                    <div class="flex items-center justify-between pt-3 mb-3">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Taxas do TPA</p>
                        @if ($tpaRemainingCurrencies->isNotEmpty())
                        <button @click="activeTpaId = {{ $tpa->id }}; showFeeModal = true"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Adicionar Taxa
                        </button>
                        @endif
                    </div>

                    @if ($tpa->paymentMethodFees->isEmpty())
                    <p class="text-xs text-slate-400 py-2">Nenhuma taxa definida.</p>
                    @else
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="bg-slate-100 border-b border-slate-200">
                                    <th class="text-left font-semibold text-slate-500 px-3 py-2">Label</th>
                                    <th class="text-right font-semibold text-slate-500 px-3 py-2">%</th>
                                    <th class="text-right font-semibold text-slate-500 px-3 py-2">Fixo</th>
                                    <th class="text-left font-semibold text-slate-500 px-3 py-2">Moeda</th>
                                    <th class="text-left font-semibold text-slate-500 px-3 py-2">Estado</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach ($tpa->paymentMethodFees as $fee)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2 text-slate-600">{{ $fee->label }}</td>
                                    <td class="px-3 py-2 text-right text-slate-700">{{ number_format($fee->fee_percentage * 100, 2) }}%</td>
                                    <td class="px-3 py-2 text-right text-slate-700">{{ number_format($fee->fee_fixed, 2) }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $fee->currency }}</td>
                                    <td class="px-3 py-2">
                                        @if ($fee->is_active)
                                        <span class="inline-flex items-center gap-1 text-emerald-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Ativo
                                        </span>
                                        @else
                                        <span class="inline-flex items-center gap-1 text-slate-400">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>Inativo
                                        </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <form method="POST" action="{{ route('dashboard.customers.tpas.fees.destroy', [$customer, $tpa, $fee]) }}"
                                              onsubmit="return confirm('Remover esta taxa?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-slate-400 hover:text-red-500 transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
            @empty
            <div class="px-5 py-10 text-center">
                <p class="text-sm text-slate-400">Nenhum TPA vinculado.</p>
            </div>
            @endforelse
            </div>{{-- fim sectionOpen --}}

        </div>{{-- fim card --}}

            {{-- Modal: Adicionar Taxa --}}
            @if ($customer->tpas->contains(fn ($tpa) => $remainingFeeCurrencies($tpa)->isNotEmpty()))
            <template x-teleport="body">
            <div x-show="showFeeModal" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-md" @click="showFeeModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-semibold text-slate-900">Adicionar Taxa</h3>
                        <button @click="showFeeModal = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    @foreach ($customer->tpas as $tpa)
                    @php $tpaRemainingCurrencies = $remainingFeeCurrencies($tpa); @endphp
                    @continue($tpaRemainingCurrencies->isEmpty())
                    <form x-show="activeTpaId === {{ $tpa->id }}"
                          method="POST"
                          action="{{ route('dashboard.customers.tpas.fees.store', [$customer, $tpa]) }}">
                        @csrf
                        <input type="hidden" name="payment_method" value="all">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Label</label>
                                <input type="text" name="label" required
                                       class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400"
                                       placeholder="Ex: Taxa TPA">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Taxa % <span class="normal-case text-slate-400">(ex: 0.015 = 1.5%)</span></label>
                                <input type="number" name="fee_percentage" step="0.0001" min="0" max="1" required
                                       class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400"
                                       placeholder="0.0150">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Taxa Fixa</label>
                                <input type="number" name="fee_fixed" step="0.0001" min="0" required
                                       class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400"
                                       placeholder="0.25">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Moeda</label>
                                <select name="currency" required
                                        class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 uppercase">
                                    @foreach ($tpaRemainingCurrencies as $currency)
                                    <option value="{{ $currency }}">{{ $currency }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" checked
                                           class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500/30">
                                    <span class="text-sm text-slate-600 font-medium">Ativo</span>
                                </label>
                            </div>
                        </div>
                        <div class="flex gap-3 mt-6">
                            <button type="button" @click="showFeeModal = false"
                                    class="flex-1 px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">
                                Cancelar
                            </button>
                            <button type="submit"
                                    class="flex-1 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition-colors">
                                Guardar
                            </button>
                        </div>
                    </form>
                    @endforeach
                </div>
            </div>
            </template>
            @endif

        </div>{{-- fim wrapper x-data TPAs --}}

        {{-- SecurePay --}}
        <div x-data="{
                 sectionOpen: false,
                 showFeeModal: false,
                 activeMerchantId: null,
             }">
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <button type="button"
                    @click="sectionOpen = !sectionOpen"
                    class="w-full px-5 py-4 flex items-center justify-between gap-3 text-left hover:bg-slate-50/80 transition-colors"
                    :class="sectionOpen ? 'border-b border-slate-100' : ''">
                <div class="flex items-center gap-2 min-w-0">
                    <h2 class="text-sm font-semibold text-slate-900">SecurePay</h2>
                    <span class="text-xs text-slate-500 bg-slate-100 px-2 py-1 rounded-full font-medium">{{ $customer->merchants->count() }}</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <img src="{{ asset('securepay.png') }}" alt="SecurePay" class="h-7">
                    <svg class="w-4 h-4 text-slate-400 transition-transform" :class="sectionOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </button>

            <div x-show="sectionOpen" x-cloak>
            @forelse ($customer->merchants as $merchant)
            @php
                $modeBadge = $merchant->mode === 'live'
                    ? ['badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Live']
                    : ['badge' => 'bg-amber-50 text-amber-700 border-amber-200', 'dot' => 'bg-amber-500', 'label' => 'Test'];
                $activeBadge = $merchant->is_active
                    ? ['badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Activo']
                    : ['badge' => 'bg-slate-100 text-slate-500 border-slate-200', 'dot' => 'bg-slate-400', 'label' => 'Inactivo'];
                $currencies = collect($merchant->allowed_currencies ?? [])->filter()->map(fn ($c) => strtoupper($c))->implode(', ');
                $merchantRemainingCurrencies = $remainingFeeCurrencies($merchant, $merchant->allowed_currencies);
            @endphp
            <div class="border-b border-slate-50 last:border-0">
                <div class="px-5 py-4 space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $merchant->name }}</p>
                            <p class="text-xs text-slate-400 font-mono mt-0.5 truncate">{{ $merchant->slug }}</p>
                        </div>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $modeBadge['badge'] }}">
                                <span class="w-1.5 h-1.5 rounded-full mr-1 {{ $modeBadge['dot'] }}"></span>
                                {{ $modeBadge['label'] }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $activeBadge['badge'] }}">
                                <span class="w-1.5 h-1.5 rounded-full mr-1 {{ $activeBadge['dot'] }}"></span>
                                {{ $activeBadge['label'] }}
                            </span>
                            @if($merchant->paymentMethodFees->count() > 0)
                                <span class="text-xs text-indigo-600 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-full font-medium">
                                    Taxa Personalizada
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                        <div>
                            <p class="text-xs text-slate-400 font-medium mb-0.5">ID</p>
                            <p class="text-sm text-slate-800 font-mono">{{ $merchant->id }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 font-medium mb-0.5">Criado</p>
                            <p class="text-sm text-slate-800">{{ $formatDateTime($merchant->created_at) }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-xs text-slate-400 font-medium mb-0.5">Webhook URL</p>
                            <p class="text-sm text-slate-800 break-all">{{ $merchant->webhook_url ?: '—' }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-xs text-slate-400 font-medium mb-0.5">Moedas permitidas</p>
                            <p class="text-sm text-slate-800">{{ $currencies !== '' ? $currencies : 'Todas' }}</p>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Taxa</p>
                            @if ($merchantRemainingCurrencies->isNotEmpty())
                            <button @click="activeMerchantId = {{ $merchant->id }}; showFeeModal = true"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Adicionar Taxa
                            </button>
                            @endif
                        </div>

                        @if ($merchant->paymentMethodFees->isEmpty())
                        <p class="text-xs text-slate-400 py-2">Nenhuma taxa personalizada. Veja as o valor das taxas <a href="{{  route('dashboard.stripe-fees') }}" style="color:#000" > clicando aqui </a>
                        @else
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="bg-slate-100 border-b border-slate-200">
                                        <th class="text-left font-semibold text-slate-500 px-3 py-2">Label</th>
                                        <th class="text-right font-semibold text-slate-500 px-3 py-2">%</th>
                                        <th class="text-right font-semibold text-slate-500 px-3 py-2">Fixo</th>
                                        <th class="text-left font-semibold text-slate-500 px-3 py-2">Moeda</th>
                                        <th class="text-left font-semibold text-slate-500 px-3 py-2">Estado</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach ($merchant->paymentMethodFees as $fee)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-3 py-2 text-slate-600">{{ $fee->label }}</td>
                                        <td class="px-3 py-2 text-right text-slate-700">{{ number_format($fee->fee_percentage * 100, 2) }}%</td>
                                        <td class="px-3 py-2 text-right text-slate-700">{{ number_format($fee->fee_fixed, 2) }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ $fee->currency }}</td>
                                        <td class="px-3 py-2">
                                            @if ($fee->is_active)
                                            <span class="inline-flex items-center gap-1 text-emerald-700">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Ativo
                                            </span>
                                            @else
                                            <span class="inline-flex items-center gap-1 text-slate-400">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>Inativo
                                            </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <form method="POST" action="{{ route('dashboard.customers.merchants.fees.destroy', [$customer, $merchant, $fee]) }}"
                                                  onsubmit="return confirm('Remover esta taxa?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-slate-400 hover:text-red-500 transition-colors">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Transações</p>
                            <span class="text-xs text-slate-400">{{ $merchant->paymentRequests->count() }} recentes</span>
                        </div>

                        @if ($merchant->paymentRequests->isEmpty())
                        <p class="text-xs text-slate-400 py-2">Nenhuma transação SecurePay.</p>
                        @else
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="bg-slate-100 border-b border-slate-200">
                                        <th class="text-left font-semibold text-slate-500 px-3 py-2">Data</th>
                                        <th class="text-left font-semibold text-slate-500 px-3 py-2">UUID</th>
                                        <th class="text-right font-semibold text-slate-500 px-3 py-2">Valor</th>
                                        <th class="text-right font-semibold text-slate-500 px-3 py-2">Taxas</th>
                                        <th class="text-right font-semibold text-slate-500 px-3 py-2">Líquido</th>
                                        <th class="text-left font-semibold text-slate-500 px-3 py-2">Estado</th>
                                        <th class="text-left font-semibold text-slate-500 px-3 py-2">Liquidação</th>
                                        <th class="text-left font-semibold text-slate-500 px-3 py-2">Ref.</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach ($merchant->paymentRequests as $pr)
                                    @php
                                        $prStatus = match ($pr->status) {
                                            'completed'  => ['badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'label' => 'Pago'],
                                            'processing' => ['badge' => 'bg-sky-50 text-sky-700 border-sky-200', 'label' => 'A processar'],
                                            'pending'    => ['badge' => 'bg-amber-50 text-amber-700 border-amber-200', 'label' => 'Pendente'],
                                            'failed'     => ['badge' => 'bg-red-50 text-red-700 border-red-200', 'label' => 'Falhou'],
                                            'expired'    => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200', 'label' => 'Expirado'],
                                            'cancelled'  => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200', 'label' => 'Cancelado'],
                                            default      => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200', 'label' => $pr->status],
                                        };
                                        $funding = $pr->stripeTransaction?->partner_funding_status;
                                        $settlement = match (true) {
                                            $pr->status !== 'completed' => ['label' => '—', 'class' => 'text-slate-400'],
                                            $funding === \App\Domains\Wallet\Enums\PartnerFundingStatus::Completed => ['label' => 'Disponível', 'class' => 'text-emerald-700'],
                                            $funding === \App\Domains\Wallet\Enums\PartnerFundingStatus::Failed => ['label' => 'Falhou', 'class' => 'text-red-700'],
                                            $funding === \App\Domains\Wallet\Enums\PartnerFundingStatus::AwaitingStripePayout => ['label' => 'Aguardar Liquidez', 'class' => 'text-amber-700'],
                                            $funding === \App\Domains\Wallet\Enums\PartnerFundingStatus::AwaitingLiquidity => ['label' => 'A aguardar liquidez', 'class' => 'text-amber-700'],
                                            $funding === \App\Domains\Wallet\Enums\PartnerFundingStatus::Pending => ['label' => 'Pendente', 'class' => 'text-amber-700'],
                                            default => ['label' => 'Pendente', 'class' => 'text-amber-700'],
                                        };
                                        $feeTotal = $pr->stripeTransaction?->resolvedFeeTotal();
                                        $netAmount = $pr->stripeTransaction?->resolvedNetAmount();
                                    @endphp
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-3 py-2 text-slate-600 whitespace-nowrap">
                                            {{ $formatDateTime($pr->paid_at ?? $pr->created_at) }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <a href="{{ url('/pay/'.$pr->uuid) }}" target="_blank" rel="noopener"
                                               class="font-mono text-indigo-600 hover:underline">
                                                {{ \Illuminate\Support\Str::limit($pr->uuid, 8, '') }}…
                                            </a>
                                        </td>
                                        <td class="px-3 py-2 text-right font-medium text-slate-800 whitespace-nowrap">
                                            {{ number_format($pr->amount / 100, 2, ',', '.') }}
                                            <span class="text-slate-400 font-normal">{{ strtoupper($pr->currency) }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-right text-slate-600 whitespace-nowrap">
                                            @if ($feeTotal === null && $pr->status === 'completed')
                                                —
                                            @elseif ($feeTotal === null)
                                                —
                                            @else
                                                {{ number_format($feeTotal / 100, 2, ',', '.') }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right text-slate-700 whitespace-nowrap">
                                            @if ($netAmount === null)
                                                —
                                            @else
                                                {{ number_format($netAmount / 100, 2, ',', '.') }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $prStatus['badge'] }}">
                                                {{ $prStatus['label'] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 {{ $settlement['class'] }} font-medium whitespace-nowrap">
                                            {{ $settlement['label'] }} → {{ \Carbon\Carbon::parse($pr->stripeTransaction?->available_on)->format('d/m/Y') }}
                                        </td>
                                        <td class="px-3 py-2 text-slate-500 max-w-[8rem] truncate" title="{{ $pr->external_reference }}">
                                            {{ $pr->external_reference ?: '—' }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="px-5 py-10 text-center">
                <p class="text-sm text-slate-400">Nenhum SecurePay registado.</p>
            </div>
            @endforelse
            </div>{{-- fim sectionOpen --}}

        </div>{{-- fim card --}}

            {{-- Modal: Adicionar Taxa SecurePay --}}
            @if ($customer->merchants->contains(fn ($merchant) => $remainingFeeCurrencies($merchant, $merchant->allowed_currencies)->isNotEmpty()))
            <template x-teleport="body">
            <div x-show="showFeeModal" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-md" @click="showFeeModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-semibold text-slate-900">Adicionar Taxa</h3>
                        <button @click="showFeeModal = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    @foreach ($customer->merchants as $merchant)
                    @php $merchantRemainingCurrencies = $remainingFeeCurrencies($merchant, $merchant->allowed_currencies); @endphp
                    @continue($merchantRemainingCurrencies->isEmpty())
                    <form x-show="activeMerchantId === {{ $merchant->id }}"
                          method="POST"
                          action="{{ route('dashboard.customers.merchants.fees.store', [$customer, $merchant]) }}">
                        @csrf
                        <input type="hidden" name="payment_method" value="all">
                        <div class="grid grid-cols-2 gap-4">

                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Label</label>
                                <input type="text" name="label" required
                                       class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400"
                                       placeholder="Ex: Taxa padrão">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Taxa % <span class="normal-case text-slate-400">(ex: 0.015 = 1.5%)</span></label>
                                <input type="number" name="fee_percentage" step="0.0001" min="0" max="100" required
                                       class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400"
                                       placeholder="0.0150">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Taxa Fixa</label>
                                <input type="number" name="fee_fixed" step="0.0001" min="0" required
                                       class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400"
                                       placeholder="0.25">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Moeda</label>
                                <select name="currency" required
                                        class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 uppercase">
                                    @foreach ($merchantRemainingCurrencies as $currency)
                                    <option value="{{ $currency }}">{{ $currency }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" checked
                                           class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500/30">
                                    <span class="text-sm text-slate-600 font-medium">Ativo</span>
                                </label>
                            </div>
                        </div>
                        <div class="flex gap-3 mt-6">
                            <button type="button" @click="showFeeModal = false"
                                    class="flex-1 px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">
                                Cancelar
                            </button>
                            <button type="submit"
                                    class="flex-1 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition-colors">
                                Guardar
                            </button>
                        </div>
                    </form>
                    @endforeach
                </div>
            </div>
            </template>
            @endif

        </div>{{-- fim wrapper x-data SecurePay --}}

    </div>{{-- fim LEFT COLUMN --}}

    {{-- ==================== RIGHT COLUMN ==================== --}}
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-6"
             x-data="{
                 sectionOpen: false,
                 showBalanceModal: false,
                 showSmsModal: {{ $pendingBalanceAdjustmentPayload ? 'true' : 'false' }},
                 balanceDirection: 'credit',
                 amount: '',
                 description: '',
                 smsCode: '',
                 pendingAdjustment: {{ \Illuminate\Support\Js::from($pendingBalanceAdjustmentPayload) }},
                 isSubmitting: false,
                 isConfirming: false,
                 errorMessage: '',
                 async postJson(url, payload) {
                     const response = await fetch(url, {
                         method: 'POST',
                         headers: {
                             'Content-Type': 'application/json',
                             'Accept': 'application/json',
                             'X-Requested-With': 'XMLHttpRequest',
                             'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                         },
                         body: JSON.stringify(payload),
                     });

                     const json = await response.json().catch(() => ({}));
                     if (! response.ok) {
                         throw new Error(json.message || 'Ocorreu um erro inesperado.');
                     }

                     return json;
                 },
                 resetRequestForm() {
                     this.amount = '';
                     this.description = '';
                     this.smsCode = '';
                 },
                 async submitAdjustment() {
                     this.isSubmitting = true;
                     this.errorMessage = '';

                     try {
                         const json = await this.postJson('{{ route('dashboard.customers.balance-adjustments.request', $customer) }}', {
                             direction: this.balanceDirection,
                             amount: this.amount,
                             description: this.description,
                         });

                         this.pendingAdjustment = json.adjustment;
                         this.showBalanceModal = false;
                         this.showSmsModal = true;
                         this.smsCode = '';
                         this.resetRequestForm();
                     } catch (error) {
                         this.errorMessage = error.message;
                     } finally {
                         this.isSubmitting = false;
                     }
                 },
                 async confirmAdjustment() {
                     if (! this.pendingAdjustment) {
                         return;
                     }

                     this.isConfirming = true;
                     this.errorMessage = '';

                     try {
                         const json = await this.postJson('{{ route('dashboard.customers.balance-adjustments.confirm', [$customer, '__ADJUSTMENT__']) }}'.replace('__ADJUSTMENT__', this.pendingAdjustment.id), {
                             code: this.smsCode,
                         });

                         this.pendingAdjustment = json.adjustment;
                         this.smsCode = '';

                         if (json.adjustment?.status === 'executed') {
                             this.showSmsModal = false;
                             window.location.reload();
                         }
                     } catch (error) {
                         this.errorMessage = error.message;
                     } finally {
                         this.isConfirming = false;
                     }
                 },
                 async resendSms() {
                     if (! this.pendingAdjustment) {
                         return;
                     }

                     this.errorMessage = '';

                     try {
                         const json = await this.postJson('{{ route('dashboard.customers.balance-adjustments.resend', [$customer, '__ADJUSTMENT__']) }}'.replace('__ADJUSTMENT__', this.pendingAdjustment.id), {});
                         this.pendingAdjustment = json.adjustment;
                     } catch (error) {
                         this.errorMessage = error.message;
                     }
                 },
                 async cancelAdjustment() {
                     if (! this.pendingAdjustment) {
                         return;
                     }

                     this.errorMessage = '';

                     try {
                         await this.postJson('{{ route('dashboard.customers.balance-adjustments.cancel', [$customer, '__ADJUSTMENT__']) }}'.replace('__ADJUSTMENT__', this.pendingAdjustment.id), {});
                         this.pendingAdjustment = null;
                         this.showSmsModal = false;
                         this.smsCode = '';
                     } catch (error) {
                         this.errorMessage = error.message;
                     }
                 }
             }">
        {{-- Saldo --}}
        @php
            $isPlatformCustomer = $customer->type === 'platform';
            $platformMasterAccounts = $isPlatformCustomer
                ? $customer->platformWallesterMainAccounts
                : collect();
            $platformFeeAccounts = $isPlatformCustomer
                ? $customer->platformWallesterFeeAccounts
                : collect();
            $showPlatformSaldo = $isPlatformCustomer && ($platformMasterAccounts->isNotEmpty() || $platformFeeAccounts->isNotEmpty());
            $showCustomerSaldo = $customer->balances->isNotEmpty();
        @endphp
        @if ($showPlatformSaldo || $showCustomerSaldo)
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                <h2 class="text-sm font-semibold text-slate-900">Saldo</h2>
            </div>
            @if ($showPlatformSaldo)
            <div class="divide-y divide-slate-50">
                @foreach ($platformMasterAccounts as $masterAccount)
                @php $masterCurrency = strtoupper($masterAccount->funding_currency ?: $masterAccount->currency_code ?: 'EUR'); @endphp
                <div class="px-5 py-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ $masterCurrency }} · Caixa</span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                <span class="text-xs text-slate-500">Disponível</span>
                            </div>
                            <span class="text-sm font-bold text-emerald-600">
                                {{ number_format(((int) $masterAccount->available_amount) / 100, 2, ',', '.') }}
                                <span class="text-xs font-normal text-slate-400">{{ $masterCurrency }}</span>
                            </span>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-3">Caixa Wallester · master. Lucro (taxas Stripe e câmbio) vai para a Sub Conta Master.@can('transactions.view') Ver também <a href="{{ route('dashboard.platform-revenue') }}" class="text-emerald-700 hover:underline">Receita Platform</a>.@endcan</p>
                </div>
                @endforeach
                @foreach ($platformFeeAccounts as $feeAccount)
                @php $feeCurrency = strtoupper($feeAccount->funding_currency ?: $feeAccount->currency_code ?: 'EUR'); @endphp
                <div class="px-5 py-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-violet-500 uppercase tracking-widest">{{ $feeAccount->receiver_name ?: 'Sub Conta Master' }}</span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-violet-400"></span>
                                <span class="text-xs text-slate-500">Ganhos da plataforma</span>
                            </div>
                            <span class="text-sm font-bold text-violet-600">
                                {{ number_format(((int) $feeAccount->available_amount) / 100, 2, ',', '.') }}
                                <span class="text-xs font-normal text-slate-400">{{ $feeCurrency }}</span>
                            </span>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-3 font-mono">{{ $feeAccount->wallester_account_id }}</p>
                </div>
                @endforeach
            </div>
            @elseif ($showCustomerSaldo)
            <div class="divide-y divide-slate-50">
                @foreach ($customer->balances as $balance)
                @php
                    $captiveTransfers = $customer->wiseTransfers
                        ->filter(fn ($transfer) => strtoupper((string) $transfer->currency) === strtoupper((string) $balance->currency)
                            && $transfer->settled_at === null
                            && $transfer->refunded_at === null);
                    $captiveCents = (int) $captiveTransfers->sum('amount_cents');
                @endphp
                <div class="px-5 py-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ $balance->currency }}</span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                <span class="text-xs text-slate-500">Disponível</span>
                            </div>
                            <span class="text-sm font-bold text-emerald-600">
                                {{ number_format($balance->wise_allocated / 100, 2, ',', '.') }}
                                <span class="text-xs font-normal text-slate-400">{{ strtoupper($balance->currency) }}</span>
                            </span>
                        </div>
                        @if ($captiveCents > 0)
                        <div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-sky-400"></span>
                                    <span class="text-xs text-slate-500">Cativo</span>
                                </div>
                                <span class="text-sm font-semibold text-sky-700">
                                    {{ number_format($captiveCents / 100, 2, ',', '.') }}
                                    <span class="text-xs font-normal text-slate-400">{{ strtoupper($balance->currency) }}</span>
                                </span>
                            </div>
                            @foreach ($captiveTransfers as $transfer)
                            <p class="text-[11px] text-slate-400 mt-1 pl-3.5">
                                {{ $transfer->beneficiary_name }} · {{ $transfer->reference }}
                            </p>
                            @endforeach
                        </div>
                        @endif
                        {{-- @if ($balance->pending > 0) --}}
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                <span class="text-xs text-slate-500">Pendente</span>
                            </div>
                            <span class="text-sm font-semibold text-amber-600">
                                {{ number_format($balance->pending / 100, 2, ',', '.') }}
                                <span class="text-xs font-normal text-slate-400">{{ strtoupper($balance->currency) }}</span>
                            </span>
                        </div>
                        {{-- @endif --}}
                        <div class="pt-1 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs text-slate-400">Total</span>
                            <span class="text-sm font-bold text-slate-800">
                                {{ number_format(($balance->wallester_allocated + $balance->pending + $captiveCents) / 100, 2, ',', '.') }}
                                <span class="text-xs font-normal text-slate-400">{{ strtoupper($balance->currency) }}</span>
                            </span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            @can('customers.adjustments_balance')
                <div class="px-5 py-4 space-y-3 {{ ($showPlatformSaldo || $showCustomerSaldo) ? 'border-t border-slate-100' : '' }}">
                    <div x-show="pendingAdjustment" x-cloak
                        class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 space-y-3">
                        <div>
                            <p class="text-xs font-semibold text-amber-800 uppercase tracking-wider">Confirmação SMS pendente</p>
                            <p class="text-sm text-amber-900 mt-1">
                                <span x-text="pendingAdjustment?.direction_label"></span>
                                de <span x-text="pendingAdjustment?.amount_formatted"></span>
                                <span x-text="pendingAdjustment?.currency"></span>
                            </p>
                            <p class="text-xs text-amber-700 mt-1" x-text="pendingAdjustment?.description"></p>
                        </div>
                        <button type="button" @click="showSmsModal = true" class="btn-secondary btn-sm w-full">
                            Inserir código SMS
                        </button>
                    </div>

                    <div class="flex gap-2" x-show="!pendingAdjustment">
                        <button type="button"
                                @click="balanceDirection = 'credit'; showBalanceModal = true"
                                class="btn-secondary btn-sm flex-1 inline-flex items-center justify-center gap-1.5">
                            Creditar
                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                            </svg>
                        </button>
                        <button type="button"
                                @click="balanceDirection = 'debit'; showBalanceModal = true"
                                class="btn-secondary btn-sm flex-1 inline-flex items-center justify-center gap-1.5">
                            Debitar
                            <svg class="w-3.5 h-3.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @endcan
        </div>
        @endif

        {{-- Conta Wallester --}}
        @if ($customer->wallesterAccount)
        <div class="bg-white rounded-2xl border {{ $wallesterFailed ? 'border-red-200' : 'border-slate-200/60' }} shadow-sm overflow-hidden">
            @php $wallesterCardCount = $customer->wallesterAccount->cards->count(); @endphp
            <button type="button"
                    @click="sectionOpen = !sectionOpen"
                    class="w-full px-5 py-4 flex items-center justify-between gap-3 text-left hover:bg-slate-50/80 transition-colors"
                    :class="sectionOpen ? 'border-b border-slate-100' : ''">
                <div class="flex items-center gap-2 min-w-0">
                    <svg class="w-4 h-4 text-violet-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                            Conta Wallester
                            @if ($wallesterFailed)
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-200">FAILED</span>
                            @endif
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5 truncate">
                            {{ $wallesterCardCount === 1 ? '1 cartão' : $wallesterCardCount.' cartões' }}
                            · {{ $customer->wallesterAccount->wallester_account_id ?? '—' }}
                        </p>
                    </div>
                </div>
                <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform" :class="sectionOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="sectionOpen" x-cloak class="p-5">
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Account ID</span>
                    <span class="font-mono text-xs text-slate-700 bg-slate-50 px-2 py-0.5 rounded">{{ $customer->wallesterAccount->wallester_account_id ?? '—' }}</span>
                </div>
                @if ($customer->wallesterAccount->status_reason)
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 flex-shrink-0">Motivo</span>
                    <span class="text-xs text-red-700 text-right">{{ $customer->wallesterAccount->status_reason }}</span>
                </div>
                @endif

                <div class="pt-3 mt-1 border-t border-slate-100 space-y-3">
                    @php
                        $linkableWallesterCards = $customer->wallesterAccount->cards
                            ->filter(fn ($card) => (string) ($card->status ?? '') !== 'Closed')
                            ->values();
                    @endphp
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Cartões</p>
                        @can('customers.edit')
                        @if ($wallesterActive)
                        <form method="POST" action="{{ route('dashboard.customers.wallester-cards.store', $customer) }}"
                              onsubmit="return confirm('Emitir um novo cartão virtual para este cliente?')">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Adicionar cartão
                            </button>
                        </form>
                        @endif
                        @endcan
                    </div>

                    @forelse ($customer->wallesterAccount->cards as $card)
                    @php
                        $cardStatus = (string) ($card->status ?? '');
                        $cardStatusMap = match ($cardStatus) {
                            'Active' => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'Ativo'],
                            'Created' => ['bg-sky-50 text-sky-700 border-sky-200', 'Criado'],
                            'Blocked' => ['bg-red-50 text-red-700 border-red-200', 'Bloqueado'],
                            'Closed' => ['bg-slate-100 text-slate-600 border-slate-200', 'Encerrado'],
                            default => ['bg-slate-50 text-slate-600 border-slate-200', $cardStatus ?: '—'],
                        };
                        $expiryLabel = $card->expiry_date
                            ? $card->expiry_date->format('m/Y')
                            : '—';
                    @endphp
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 space-y-2">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ $card->name ?? '—' }}</p>
                                <p class="font-mono text-xs text-slate-600 mt-0.5">{{ $card->masked_card_number ?? '—' }}</p>
                            </div>
                            <span class="flex-shrink-0 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full border {{ $cardStatusMap[0] }}">
                                {{ $cardStatusMap[1] }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-slate-400">Validade</span>
                                <p class="font-medium text-slate-700 mt-0.5">{{ $expiryLabel }}</p>
                            </div>
                            <div>
                                <span class="text-slate-400">Tipo</span>
                                <p class="font-medium text-slate-700 mt-0.5">{{ $card->type ?? '—' }}</p>
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-xs text-slate-500">Nenhum cartão emitido.</p>
                    @endforelse
                </div>
            </div>
            </div>{{-- fim sectionOpen --}}

            @can('customers.adjustments_balance')
            @if (! $pendingBalanceAdjustment)
                <template x-teleport="body">
                <div x-show="showBalanceModal" x-cloak
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0">
                    <div class="absolute inset-0 bg-black/50 backdrop-blur-md" @click="showBalanceModal = false"></div>
                    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-base font-semibold text-slate-900"
                                x-text="balanceDirection === 'credit' ? 'Creditar cliente' : 'Debitar cliente'"></h3>
                            <button type="button" @click="showBalanceModal = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <form @submit.prevent="submitAdjustment()">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Valor</label>
                                    <input type="number" x-model="amount" step="0.01" min="0.01" required
                                        class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400"
                                        placeholder="0.00">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Moeda</label>
                                    <input type="text" readonly
                                        value="{{ strtoupper($customer->wallesterAccount->funding_currency ?? $customer->wallesterAccount->currency_code ?? 'EUR') }}"
                                        class="w-full px-3 py-2 text-sm bg-slate-100 border border-slate-200 rounded-xl text-slate-600">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Motivo</label>
                                    <textarea x-model="description" required rows="3" maxlength="500"
                                            class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400"
                                            placeholder="Descreva o motivo do ajuste"></textarea>
                                </div>
                                <p class="text-xs text-slate-500">
                                    Será enviado um SMS com código de autenticação para o seu telefone de super-admin.
                                </p>
                                <p x-show="errorMessage" x-text="errorMessage" class="text-xs text-red-600"></p>
                            </div>
                            <div class="flex gap-3 mt-6">
                                <button type="button" @click="showBalanceModal = false" class="btn-secondary flex-1">Cancelar</button>
                                <button type="submit" class="btn-primary flex-1 disabled:opacity-60"
                                        :disabled="isSubmitting"
                                        x-text="balanceDirection === 'credit' ? 'Solicitar crédito' : 'Solicitar débito'"></button>
                            </div>
                        </form>
                    </div>
                </div>
                </template>
            @endif

            <template x-teleport="body">
            <div x-show="showSmsModal" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-md" @click="showSmsModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Confirmar com SMS</h3>
                            <p class="text-sm text-slate-500 mt-1" x-show="pendingAdjustment">
                                <span x-text="pendingAdjustment?.direction_label"></span>
                                de <span x-text="pendingAdjustment?.amount_formatted"></span>
                                <span x-text="pendingAdjustment?.currency"></span>
                            </p>
                        </div>
                        <button type="button" @click="showSmsModal = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <template x-if="pendingAdjustment">
                        <div class="space-y-4">
                            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-3">
                                <p class="text-xs text-amber-800 font-semibold uppercase tracking-wider">Motivo</p>
                                <p class="text-sm text-amber-900 mt-1" x-text="pendingAdjustment.description"></p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Código SMS</label>
                                <input type="text" x-model="smsCode" maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                                       class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/30 focus:border-amber-400"
                                       placeholder="000000">
                            </div>

                            <p x-show="errorMessage" x-text="errorMessage" class="text-xs text-red-600"></p>

                            <div class="flex gap-2">
                                <button type="button" @click="resendSms()" class="btn-secondary btn-sm flex-1">Reenviar SMS</button>
                                <button type="button" @click="cancelAdjustment()" class="btn-secondary btn-sm flex-1 text-red-600">Cancelar</button>
                            </div>

                            <div class="flex gap-3">
                                <button type="button" @click="showSmsModal = false" class="btn-secondary flex-1">Fechar</button>
                                <button type="button" @click="confirmAdjustment()" class="btn-primary flex-1 disabled:opacity-60"
                                        :disabled="isConfirming || smsCode.length !== 6">
                                    Confirmar e executar
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            </template>
            @endcan
        </div>
        @endif
        </div>

        {{-- Conta Stripe --}}
        @if ($customer->stripeAccount)
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-slate-900 mb-2 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                Conta Stripe
            </h2>
            <div class="space-y-1.5 text-sm">
                <div class="flex justify-between items-center gap-4">
                    <span class="text-slate-500">Customer ID</span>
                    <span class="font-mono text-xs text-slate-700 bg-slate-50 px-2 py-0.5 rounded">{{ $customer->stripeAccount->stripe_customer_id ?? '—' }}</span>
                </div>
                @if ($stripeFundingBadge)
                <div class="flex justify-between items-center gap-4">
                    <span class="text-slate-500">Funding</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border {{ $stripeFundingBadge['badge'] }}">{{ $stripeFundingBadge['label'] }}</span>
                </div>
                @endif
                @if ($stripeFundingActive && filled($customer->stripeAccount->funding_iban))
                <div class="flex justify-between items-center gap-4">
                    <span class="text-slate-500 flex-shrink-0">IBAN</span>
                    <span class="font-mono text-xs text-slate-700 bg-slate-50 px-2 py-0.5 rounded text-right break-all">{{ $customer->stripeAccount->funding_iban }}</span>
                </div>
                @if (filled($customer->stripeAccount->funding_bic))
                <div class="flex justify-between items-center gap-4">
                    <span class="text-slate-500 flex-shrink-0">BIC</span>
                    <span class="font-mono text-xs text-slate-700 bg-slate-50 px-2 py-0.5 rounded">{{ $customer->stripeAccount->funding_bic }}</span>
                </div>
                @endif
                @if (filled($customer->stripeAccount->funding_account_holder_name))
                <div class="flex justify-between items-center gap-4">
                    <span class="text-slate-500 flex-shrink-0">Titular</span>
                    <span class="text-xs text-slate-700 text-right">{{ $customer->stripeAccount->funding_account_holder_name }}</span>
                </div>
                @endif
                @else
                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-sm text-slate-600">
                        @if ($stripeFundingFailed)
                            A criação do IBAN falhou.
                        @elseif ($stripeFundingPending)
                            IBAN ainda não provisionado.
                        @else
                            IBAN indisponível.
                        @endif
                    </p>
                    @if (filled($customer->stripeAccount->funding_status_reason))
                    <p class="text-xs text-red-700 mt-1">{{ $customer->stripeAccount->funding_status_reason }}</p>
                    @elseif ($stripeFundingPending)
                    <p class="text-xs text-amber-700 mt-1">Aguarda conclusão do provisioning ou tente criar manualmente.</p>
                    @endif
                </div>
                @endif
                @if ($customer->stripeAccount->funding_status_changed_at)
                <div class="flex justify-between items-center gap-4">
                    <span class="text-slate-500 flex-shrink-0">Actualizado</span>
                    <span class="text-xs text-slate-600">{{ $formatDateTime($customer->stripeAccount->funding_status_changed_at) }}</span>
                </div>
                @endif
            </div>

            @can('customers.edit')
            @if ($canRetryStripeFunding)
            <form method="POST" action="{{ route('dashboard.customers.retry-stripe-funding', $customer) }}" class="mt-3">
                @csrf
                <button type="submit" class="btn-secondary btn-sm w-full sm:w-auto">
                    {{ $stripeFundingFailed ? 'Repetir criação do IBAN' : 'Criar IBAN' }}
                </button>
            </form>
            @endif
            @endcan
        </div>
        @endif

        @include('dashboard.partials.customer-activity-timeline', ['activityTimeline' => $activityTimeline])

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

        {{-- Logs de auditoria --}}
        @can('audit_logs.view')
        @php $recentLogs = $customer->activities()->with('causer')->latest()->limit(5)->get(); @endphp
        @if ($recentLogs->isNotEmpty())
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Auditoria</h2>
                <a href="{{ route('dashboard.audit-logs', ['log' => 'customers', 'search' => $customer->id]) }}"
                   class="text-xs text-indigo-600 hover:underline">Ver tudo</a>
            </div>
            <div class="divide-y divide-slate-50">
                @foreach ($recentLogs as $log)
                @php
                    $ev = $log->event ?? $log->description ?? 'updated';
                    $evColor = match($ev) {
                        'created' => 'bg-emerald-500',
                        'updated' => 'bg-amber-500',
                        'deleted' => 'bg-red-500',
                        default   => 'bg-slate-400',
                    };
                    $evLabel = match($ev) {
                        'created' => 'Criado',
                        'updated' => 'Atualizado',
                        'deleted' => 'Removido',
                        default   => ucfirst($ev),
                    };
                @endphp
                <div class="px-5 py-3 flex items-center gap-3">
                    <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $evColor }}"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-slate-700">{{ $evLabel }}
                            <span class="text-slate-400 font-normal">por {{ $log->causer?->name ?? 'Sistema' }}</span>
                        </p>
                        <p class="text-xs text-slate-400">{{ $log->created_at?->format('d/m/Y H:i') }}</p>
                    </div>
                    <a href="{{ route('dashboard.audit-logs.show', $log) }}"
                       class="text-xs text-slate-400 hover:text-indigo-600 transition-colors flex-shrink-0">Ver</a>
                </div>
                @endforeach
            </div>
        </div>
        @endif
        @endcan

    </div>{{-- fim RIGHT COLUMN --}}
</div>

@endsection
