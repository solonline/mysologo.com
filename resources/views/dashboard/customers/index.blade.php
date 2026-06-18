@extends('layouts.dashboard')

@section('title', 'Clientes')
@section('breadcrumb', 'Clientes')

@section('page-header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Clientes</h1>
        <p class="mt-1 text-sm text-slate-500">Gerencie todos os clientes cadastrados no sistema.</p>
    </div>
</div>
@endsection

@section('content')

{{-- ==================== STAT CARDS ==================== --}}
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    @php
        $cardStats = [
            ['label' => 'Total',     'value' => $stats['total'],   'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'color' => 'text-indigo-600 bg-indigo-50'],
            ['label' => 'Ativos',    'value' => $stats['active'],  'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'text-emerald-600 bg-emerald-50'],
            ['label' => 'Pendentes', 'value' => $stats['pending'], 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'text-amber-600 bg-amber-50'],
            ['label' => 'Bloqueados','value' => $stats['blocked'], 'icon' => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636', 'color' => 'text-red-600 bg-red-50'],
        ];
    @endphp
    @foreach ($cardStats as $card)
    <div class="bg-white rounded-2xl p-4 border border-slate-200/60 shadow-sm flex items-center gap-4">
        <div class="p-2.5 rounded-xl {{ $card['color'] }} flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}"/>
            </svg>
        </div>
        <div>
            <p class="text-xl font-bold text-slate-900">{{ number_format($card['value']) }}</p>
            <p class="text-xs text-slate-500 font-medium">{{ $card['label'] }}</p>
        </div>
    </div>
    @endforeach
</div>

{{-- ==================== FILTERS ==================== --}}
<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">

    <div class="px-5 py-4 border-b border-slate-100">
        <form method="GET" action="{{ route('dashboard.customers') }}" class="flex flex-wrap items-center gap-3">
            {{-- Search --}}
            <div class="relative flex-1 min-w-48">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Buscar por nome, email, telefone ou nº documento..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition">
            </div>

            {{-- Customer Status --}}
            <select name="status"
                    class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 text-slate-700 transition">
                <option value="">Todos os status</option>
                @foreach ($customerStatuses as $s)
                    <option value="{{ $s->value }}" {{ request('status') === $s->value ? 'selected' : '' }}>{{ $s->label() }}</option>
                @endforeach
            </select>

            {{-- Doc Status --}}
            <select name="doc_status"
                    class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 text-slate-700 transition">
                <option value="">Doc. status</option>
                @foreach ($documentStatuses as $s)
                    @php
                        $label = match($s->value) {
                            'APPROVED'  => 'Aprovado',
                            'PENDING'   => 'Pendente',
                            'ANALYSING' => 'Em Análise',
                            'DECLINED'  => 'Recusado',
                            default     => $s->value,
                        };
                    @endphp
                    <option value="{{ $s->value }}" {{ request('doc_status') === $s->value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            {{-- Type --}}
            <select name="type"
                    class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 text-slate-700 transition">
                <option value="">Tipo</option>
                <option value="retail"    {{ request('type') === 'retail'    ? 'selected' : '' }}>Retail</option>
                <option value="corporate" {{ request('type') === 'corporate' ? 'selected' : '' }}>Corporate</option>
            </select>

            <button type="submit"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
                Filtrar
            </button>

            @if (request()->hasAny(['search', 'status', 'doc_status', 'type']))
            <a href="{{ route('dashboard.customers') }}"
               class="px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition">
                Limpar
            </a>
            @endif
        </form>
    </div>

    {{-- ==================== TABLE ==================== --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-100">
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-5 py-3">Cliente</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 hidden sm:table-cell">Telefone</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 hidden md:table-cell">Tipo</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Status</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 hidden lg:table-cell">Doc. Status</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 hidden xl:table-cell">Docs</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 hidden md:table-cell">Cadastro</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody id="customers-tbody" class="divide-y divide-slate-100">
                @forelse ($customers as $customer)
                @php
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
                <tr class="hover:bg-slate-50/50 transition-colors group">
                    {{-- Cliente --}}
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500
                                        flex items-center justify-center text-white text-xs font-bold flex-shrink-0 shadow-sm">
                                {{ strtoupper(substr($customer->name ?? 'C', 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ $customer->name }}</p>
                                <p class="text-xs text-slate-400 truncate">{{ $customer->email }}</p>
                            </div>
                        </div>
                    </td>
                    {{-- Telefone --}}
                    <td class="px-4 py-3.5 hidden sm:table-cell">
                        <span class="text-sm text-slate-600">{{ $customer->phone ?? '—' }}</span>
                    </td>
                    {{-- Tipo --}}
                    <td class="px-4 py-3.5 hidden md:table-cell">
                        @if ($customer->type === 'corporate')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-50 text-violet-700 border border-violet-200">Corporate</span>
                        @elseif ($customer->type === 'retail')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">Retail</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-400 border border-slate-200">{{ ucfirst($customer->type) }}</span>
                        @endif
                    </td>
                    {{-- Status --}}
                    <td class="px-4 py-3.5">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $custBadge['badge'] }}">
                            <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $custBadge['dot'] }}"></span>
                            {{ $custBadge['label'] }}
                        </span>
                    </td>
                    {{-- Doc Status --}}
                    <td class="px-4 py-3.5 hidden lg:table-cell">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $docBadge['badge'] }}">
                            <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $docBadge['dot'] }}"></span>
                            {{ __($docBadge['label']) }}
                        </span>
                    </td>
                    {{-- Docs --}}
                    <td class="px-4 py-3.5 hidden xl:table-cell">
                        @php $doc = $customer->documents->first(); @endphp
                        @if ($doc)
                            <span class="block text-sm text-slate-700 font-medium">{{ $doc->document_type ?? '—' }}</span>
                            <span class="block text-xs text-slate-500">{{ $doc->document_number ?? '—' }}</span>
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </td>
                    {{-- Data --}}
                    <td class="px-4 py-3.5 hidden md:table-cell">
                        <span class="text-sm text-slate-500">{{ $customer->created_at?->format('d/m/Y') }}</span>
                    </td>
                    {{-- Ações --}}
                    <td class="px-4 py-3.5 text-right">
                        <a href="{{ route('dashboard.customers.show', $customer) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-600
                                  bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg transition-colors
                                  opacity-0 group-hover:opacity-100">
                            Ver
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-5 py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center">
                                <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-700">Nenhum cliente encontrado</p>
                                <p class="text-xs text-slate-400 mt-1">Tente ajustar os filtros de busca.</p>
                            </div>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ==================== PAGINATION ==================== --}}
    <div id="customers-pagination">
    @if ($customers->hasPages())
    <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
        <p class="text-xs text-slate-500">
            Mostrando <span class="font-semibold text-slate-700">{{ $customers->firstItem() }}</span>–<span class="font-semibold text-slate-700">{{ $customers->lastItem() }}</span>
            de <span class="font-semibold text-slate-700">{{ $customers->total() }}</span> clientes
        </p>
        <div class="flex items-center gap-1">
            {{-- Prev --}}
            @if ($customers->onFirstPage())
                <span class="px-3 py-1.5 text-xs text-slate-300 bg-slate-50 border border-slate-200 rounded-lg cursor-not-allowed">← Anterior</span>
            @else
                <a href="{{ $customers->previousPageUrl() }}"
                   class="px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">← Anterior</a>
            @endif

            {{-- Pages --}}
            @foreach ($customers->getUrlRange(max(1, $customers->currentPage() - 2), min($customers->lastPage(), $customers->currentPage() + 2)) as $page => $url)
                @if ($page === $customers->currentPage())
                    <span class="px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 border border-indigo-600 rounded-lg">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">{{ $page }}</a>
                @endif
            @endforeach

            {{-- Next --}}
            @if ($customers->hasMorePages())
                <a href="{{ $customers->nextPageUrl() }}"
                   class="px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">Próximo →</a>
            @else
                <span class="px-3 py-1.5 text-xs text-slate-300 bg-slate-50 border border-slate-200 rounded-lg cursor-not-allowed">Próximo →</span>
            @endif
        </div>
    </div>
    @endif
    </div>{{-- #customers-pagination --}}

</div>

@push('scripts')
<script>
(function () {
    const searchInput = document.querySelector('[name="search"]');
    const tbody       = document.getElementById('customers-tbody');
    const pagination  = document.getElementById('customers-pagination');
    let debounceTimer = null;
    let lastSearch    = searchInput ? searchInput.value : '';

    if (!searchInput || !tbody) return;

    function getFormParams(searchValue) {
        const form   = searchInput.closest('form');
        const params = new URLSearchParams(new FormData(form));
        params.set('search', searchValue);
        return params.toString();
    }

    function doSearch(value) {
        const url = window.location.pathname + '?' + getFormParams(value);

        searchInput.classList.add('opacity-60');

        axios.get(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                const parser  = new DOMParser();
                const doc     = parser.parseFromString(response.data, 'text/html');
                const newBody = doc.getElementById('customers-tbody');
                const newPage = doc.getElementById('customers-pagination');

                if (newBody) tbody.innerHTML = newBody.innerHTML;
                if (newPage && pagination) pagination.innerHTML = newPage.innerHTML;

                // Update URL without reload
                window.history.replaceState(null, '', url);
            })
            .catch(function (err) {
                console.error('Live search error', err);
            })
            .finally(function () {
                searchInput.classList.remove('opacity-60');
            });
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const value = this.value.trim();

        if (value === lastSearch) return;

        // Fire immediately on clear, wait 400ms otherwise
        const delay = (value.length === 0) ? 0 : 400;

        if (value.length > 0 && value.length < 3) return;

        debounceTimer = setTimeout(function () {
            lastSearch = value;
            doSearch(value);
        }, delay);
    });
})();
</script>
@endpush

@endsection

