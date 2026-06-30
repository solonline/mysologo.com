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
            $avatarUrl = filled($customer->avatar)
                ? (str_starts_with($customer->avatar, 'http://') || str_starts_with($customer->avatar, 'https://')
                    ? $customer->avatar
                    : Storage::url($customer->avatar))
                : null;
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
</div>
@endsection

@section('content')

@php
    $resolveMediaUrl = function (?string $path): ?string {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::url($path);
    };

    $latestDiditSession = $customer->diditSessions->first();

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
@endphp

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
                        ['label' => 'Telefone verificado', 'value' => ($phoneVerifiedAt = $customer->phoneVerifications->last()?->verified_at) ? $formatDateTime($phoneVerifiedAt, 'Não verificado') : 'Não verificado'],
                        ['label' => 'Cadastro',            'value' => $formatDateTime($customer->created_at)],
                    ];

                    if (filled($customer->status_reason)) {
                        $fields[] = ['label' => 'Motivo do estado', 'value' => $customer->status_reason];
                    }
                @endphp
                @foreach ($fields as $f)
                <div>
                    <p class="text-xs text-slate-400 font-medium mb-0.5">{{ $f['label'] }}</p>
                    <p class="text-sm text-slate-800 font-medium">{{ $f['value'] ?? '—' }}</p>
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
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Telefone</label>
                            <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}"
                                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('phone') border-red-400 @enderror">
                            @error('phone')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
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
                                <option value="{{ $ds->value }}" {{ old('doc_status', $docVal) === $ds->value ? 'selected' : '' }}>
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

        {{-- Sessão Didit --}}
        @if ($latestDiditSession)
        @php
            $diditStatus = $latestDiditSession->status ?? '—';
            $diditBadge = match (strtolower(str_replace(' ', '', $diditStatus))) {
                'approved' => ['badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Aprovado'],
                'declined' => ['badge' => 'bg-red-50 text-red-700 border-red-200', 'dot' => 'bg-red-500', 'label' => 'Recusado'],
                'inreview' => ['badge' => 'bg-sky-50 text-sky-700 border-sky-200', 'dot' => 'bg-sky-500', 'label' => 'Em análise'],
                'inprogress', 'notfinished' => ['badge' => 'bg-amber-50 text-amber-700 border-amber-200', 'dot' => 'bg-amber-500', 'label' => 'Em progresso'],
                'notstarted' => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200', 'dot' => 'bg-slate-400', 'label' => 'Não iniciado'],
                default => ['badge' => 'bg-slate-100 text-slate-500 border-slate-200', 'dot' => 'bg-slate-400', 'label' => $diditStatus],
            };
            $diditMetadata = is_array($latestDiditSession->metadata) ? $latestDiditSession->metadata : [];
        @endphp
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Verificação Didit</h2>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $diditBadge['badge'] }}">
                    <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $diditBadge['dot'] }}"></span>
                    {{ $diditBadge['label'] }}
                </span>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                <div>
                    <p class="text-xs text-slate-400 font-medium mb-0.5">Session ID</p>
                    <p class="font-mono text-xs text-slate-700 break-all">{{ $latestDiditSession->session_id }}</p>
                </div>
                @if (!empty($diditMetadata['application_id']))
                <div>
                    <p class="text-xs text-slate-400 font-medium mb-0.5">Application ID</p>
                    <p class="font-mono text-xs text-slate-700 break-all">{{ $diditMetadata['application_id'] }}</p>
                </div>
                @endif
                <div>
                    <p class="text-xs text-slate-400 font-medium mb-0.5">Workflow</p>
                    <p class="text-slate-800 font-medium">{{ $latestDiditSession->workflow_id ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium mb-0.5">Última actualização</p>
                    <p class="text-slate-800 font-medium">{{ $formatDateTime($latestDiditSession->updated_at) }}</p>
                </div>
                @if (filled($latestDiditSession->session_url))
                <div class="sm:col-span-2">
                    <a href="{{ $latestDiditSession->session_url }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-800">
                        Abrir sessão Didit
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Documentos --}}
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Documentos KYC</h2>
                <span class="text-xs text-slate-500 bg-slate-100 px-2 py-1 rounded-full font-medium">{{ $customer->documents->count() }}</span>
            </div>
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
        </div>

        {{-- TPAs --}}
        <div x-data="{
                 showFeeModal: false,
                 activeTpaId: null,
                 expandedTpa: null,
             }">
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">

            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    TPAs
                </h2>
                <span class="text-xs text-slate-500 bg-slate-100 px-2 py-1 rounded-full font-medium">{{ $customer->tpas->count() }}</span>
            </div>

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

                <div x-show="expandedTpa === {{ $tpa->id }}" x-cloak class="px-5 pb-4 bg-slate-50/60 border-t border-slate-100">
                    <div class="flex items-center justify-between pt-3 mb-3">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Taxas do TPA</p>
                        <button @click="activeTpaId = {{ $tpa->id }}; showFeeModal = true"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Adicionar Taxa
                        </button>
                    </div>

                    @if ($tpa->paymentMethodFees->isEmpty())
                    <p class="text-xs text-slate-400 py-2">Nenhuma taxa definida.</p>
                    @else
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="bg-slate-100 border-b border-slate-200">
                                    <th class="text-left font-semibold text-slate-500 px-3 py-2">Método</th>
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
                                    <td class="px-3 py-2 font-mono font-medium text-slate-700">{{ $fee->payment_method }}</td>
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

        </div>{{-- fim card --}}

            {{-- Modal: Adicionar Taxa --}}
            <div x-show="showFeeModal" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                <div class="fixed inset-0 bg-black/50 backdrop-blur-md" @click="showFeeModal = false"></div>
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
                    <form x-show="activeTpaId === {{ $tpa->id }}"
                          method="POST"
                          action="{{ route('dashboard.customers.tpas.fees.store', [$customer, $tpa]) }}">
                        @csrf
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Método de Pagamento</label>
                                <input type="text" name="payment_method" required
                                       class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400"
                                       placeholder="Ex: visa, mastercard, mbway">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Label</label>
                                <input type="text" name="label" required
                                       class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400"
                                       placeholder="Ex: Visa Crédito">
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
                                <input type="text" name="currency" maxlength="3" required value="EUR"
                                       class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 uppercase">
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

        </div>{{-- fim wrapper x-data TPAs --}}

    </div>{{-- fim LEFT COLUMN --}}

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

        {{-- Saldo --}}
        @if ($customer->balances->isNotEmpty())
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                <h2 class="text-sm font-semibold text-slate-900">Saldo</h2>
            </div>
            <div class="divide-y divide-slate-50">
                @foreach ($customer->balances as $balance)
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
                                {{ number_format($balance->available / 100, 2, ',', '.') }}
                                <span class="text-xs font-normal text-slate-400">{{ strtoupper($balance->currency) }}</span>
                            </span>
                        </div>
                        @if ($balance->pending > 0)
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
                        @endif
                        <div class="pt-1 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs text-slate-400">Total</span>
                            <span class="text-sm font-bold text-slate-800">
                                {{ number_format(($balance->available + $balance->pending) / 100, 2, ',', '.') }}
                                <span class="text-xs font-normal text-slate-400">{{ strtoupper($balance->currency) }}</span>
                            </span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Transações (timeline) --}}
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Transações</h2>
                <span class="text-xs text-slate-500 bg-slate-100 px-2 py-1 rounded-full font-medium">{{ $customer->transactions->count() }}</span>
            </div>

            @if ($customer->transactions->isEmpty())
            <div class="px-5 py-10 text-center">
                <div class="flex flex-col items-center gap-2">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <p class="text-sm text-slate-400">Nenhuma transação registada.</p>
                </div>
            </div>
            @else
            <div class="px-5 py-4">
                <div class="relative">
                    <div class="absolute left-[7px] top-2 bottom-2 w-px bg-slate-200"></div>
                    <div class="space-y-0">
                        @foreach ($customer->transactions as $tx)
                        @php
                            $isCredit    = $tx->direction?->value === 'credit' || $tx->direction === 'credit';
                            $amount      = number_format(abs($tx->amount ?? 0) / 100, 2, ',', '.');
                            $currency    = strtoupper($tx->currency ?? '');
                            $status      = strtolower($tx->status ?? '');
                            $statusBadge = match(true) {
                                in_array($status, ['succeeded', 'paid', 'complete', 'completed', 'settled']) => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                in_array($status, ['pending', 'processing', 'requires_capture'])             => 'bg-amber-50 text-amber-700 border-amber-200',
                                in_array($status, ['failed', 'canceled', 'cancelled', 'refunded', 'disputed']) => 'bg-red-50 text-red-700 border-red-200',
                                default => 'bg-slate-100 text-slate-500 border-slate-200',
                            };
                            $typeLabel = $tx->type?->label() ?? ($tx->payment_method_type ?? '—');
                            $txDate    = $tx->stripe_created_at ?? $tx->created_at;
                        @endphp
                        <div class="relative flex gap-3 {{ $loop->last ? 'pb-0' : 'pb-3' }}">
                            <div class="relative flex-shrink-0 mt-1">
                                <div class="w-3.5 h-3.5 rounded-full border-2 border-white shadow-sm z-10 relative
                                            {{ $isCredit ? 'bg-emerald-500' : 'bg-red-400' }}"></div>
                            </div>
                            <div class="flex-1 min-w-0 pb-3 {{ !$loop->last ? 'border-b border-slate-50' : '' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1 mb-0.5">
                                            <span class="text-sm font-bold {{ $isCredit ? 'text-emerald-600' : 'text-red-500' }}">
                                                {{ $isCredit ? '+' : '−' }} {{ $amount }} {{ $currency }}
                                            </span>
                                            @if ($tx->net_amount && $tx->net_amount !== $tx->amount)
                                            <span class="text-xs text-slate-400">(líq. {{ number_format($tx->net_amount / 100, 2, ',', '.') }})</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-slate-600 truncate">{{ $typeLabel }}</p>
                                        @if ($tx->description)
                                        <p class="text-xs text-slate-400 truncate mt-0.5">{{ $tx->description }}</p>
                                        @endif
                                        @if ($tx->bank_transfer_sender_name)
                                        <p class="text-xs text-slate-400 mt-0.5">
                                            De: {{ $tx->bank_transfer_sender_name }}
                                            @if ($tx->bank_transfer_iban_last4)(···{{ $tx->bank_transfer_iban_last4 }})@endif
                                        </p>
                                        @endif
                                    </div>
                                    <div class="flex-shrink-0 text-right">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium border {{ $statusBadge }}">
                                            {{ $tx->status ?? '—' }}
                                        </span>
                                        <p class="text-[10px] text-slate-400 mt-1">{{ $txDate?->format('d/m/Y') }}</p>
                                        <p class="text-[10px] text-slate-300">{{ $txDate?->format('H:i') }}</p>
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

        {{-- Histórico --}}
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-slate-900 mb-4">Histórico</h2>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Criado em</span>
                    <span class="text-slate-700 font-medium">{{ $formatDateTime($customer->created_at) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Atualizado em</span>
                    <span class="text-slate-700 font-medium">{{ $formatDateTime($customer->updated_at) }}</span>
                </div>
                @if ($customer->email_verified_at)
                <div class="flex justify-between">
                    <span class="text-slate-500">Email verificado</span>
                    <span class="text-emerald-600 font-medium">{{ $formatDateTime($customer->email_verified_at) }}</span>
                </div>
                @endif
            </div>
        </div>

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
