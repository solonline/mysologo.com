@extends('layouts.dashboard')

@section('title', 'Configurações')
@section('breadcrumb', 'Configurações')

@section('page-header')
<div>
    <h1 class="text-2xl font-bold text-slate-900">Configurações</h1>
    <p class="mt-1 text-sm text-slate-500">Gerir as configurações gerais do sistema.</p>
</div>
@endsection

@section('content')

@php
$tabs = [
    'company'  => ['label' => 'Empresa',       'title' => 'Informações da Empresa'],
    'address'  => ['label' => 'Endereço',       'title' => 'Morada'],
    'system'   => ['label' => 'Sistema',        'title' => 'Sistema'],
    'stripe'   => ['label' => 'Stripe',         'title' => 'Stripe'],
    'sms'      => ['label' => 'SMS',            'title' => 'SMS'],
    'social'   => ['label' => 'Redes Sociais',  'title' => 'Redes Sociais'],
    'currency' => ['label' => 'Moedas',         'title' => 'Configurações de Moedas'],
];

$grouped = $settings->groupBy(fn($s) => explode('.', $s->key)[0]);

// Pré-processar painéis: stripe expande em sub-cards por moeda, os outros ficam com 1 card
$panels = [];
foreach ($tabs as $group => $meta) {
    if ($group === 'stripe') {
        $byCurrency = $grouped->get('stripe', collect())->groupBy(fn($s) => strtoupper(explode('.', $s->key)[1]));
        foreach ($byCurrency as $currency => $currencySettings) {
            $panels[$group][] = ['title' => $currency, 'badge' => $currency, 'settings' => $currencySettings];
        }
    } else {
        $panels[$group][] = ['title' => $meta['title'], 'badge' => null, 'settings' => $grouped->get($group, collect())];
    }
}

$ic = 'w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400';
$lc = 'block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5';

$currencyColors = [
    'EUR' => 'bg-blue-50 text-blue-700 border-blue-200',
    'USD' => 'bg-green-50 text-green-700 border-green-200',
    'GBP' => 'bg-purple-50 text-purple-700 border-purple-200',
    'CHF' => 'bg-red-50 text-red-700 border-red-200',
];
@endphp

<form method="POST" action="{{ route('dashboard.settings.update') }}" x-data="{ tab: 'company' }">
    @csrf

    {{-- Tabs --}}
    <div class="flex gap-1 mb-6 bg-slate-100 p-1 rounded-xl w-fit">
        @foreach ($tabs as $group => $meta)
        <button type="button" @click="tab = '{{ $group }}'"
                :class="tab === '{{ $group }}' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-all">
            {{ $meta['label'] }}
        </button>
        @endforeach
    </div>

    {{-- Panels --}}
    @foreach ($tabs as $group => $meta)
    <div x-show="tab === '{{ $group }}'" @if($group !== 'company') x-cloak @endif class="space-y-4">

        @foreach ($panels[$group] as $panel)
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">

            {{-- Card header --}}
            <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
                @if ($panel['badge'])
                <span class="px-2.5 py-1 text-xs font-bold rounded-lg border {{ $currencyColors[$panel['badge']] ?? 'bg-slate-50 text-slate-700 border-slate-200' }}">
                    {{ $panel['badge'] }}
                </span>
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">
                    Conta Stripe {{ $panel['badge'] }}
                </h2>
                @else
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">
                    {{ $panel['title'] }}
                </h2>
                @endif
            </div>

            {{-- Fields --}}
            <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
                @foreach ($panel['settings'] as $setting)
                @php $isWide = in_array($setting->type, ['textarea', 'toggle', 'json']); @endphp
                <div class="{{ $isWide ? 'sm:col-span-2' : '' }}">

                    @if ($setting->type === 'toggle')
                    <div class="flex items-center justify-between p-4 bg-amber-50 border border-amber-200 rounded-xl"
                         x-data="{ on: {{ $setting->value === 'true' ? 'true' : 'false' }} }">
                        <div>
                            <p class="text-sm font-semibold text-amber-800">{{ $setting->label }}</p>
                            <p class="text-xs text-amber-600 mt-0.5">Quando activo, a API fica indisponível para os clientes.</p>
                        </div>
                        <input type="hidden" name="settings[{{ $setting->key }}]" :value="on ? 'true' : 'false'">
                        <button type="button" @click="on = !on"
                                :class="on ? 'bg-amber-500' : 'bg-slate-300'"
                                class="relative ml-4 w-11 h-6 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-amber-400/40 flex-shrink-0">
                            <span :class="on ? 'translate-x-5' : 'translate-x-0.5'"
                                  class="absolute top-0.5 left-0 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200"></span>
                        </button>
                    </div>

                    @elseif ($setting->type === 'json')
                    @php
                        $jsonItems = is_array($setting->value)
                            ? $setting->value
                            : (json_decode($setting->value, true) ?? []);
                    @endphp
                    <label class="{{ $lc }}">{{ $setting->label }}</label>
                    <div x-data="{
                            items: {{ json_encode($jsonItems) }},
                            input: '',
                            add() {
                                const v = this.input.trim().toUpperCase();
                                if (v && !this.items.includes(v)) { this.items.push(v); }
                                this.input = '';
                            },
                            remove(i) { this.items.splice(i, 1); }
                         }"
                         class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                        <div class="flex flex-wrap gap-2 min-h-[2rem]">
                            <template x-for="(item, i) in items" :key="i">
                                <span class="inline-flex items-center gap-1 px-3 py-1 bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs font-semibold rounded-full">
                                    <span x-text="item"></span>
                                    <button type="button" @click="remove(i)"
                                            class="ml-0.5 text-indigo-400 hover:text-indigo-700 leading-none">&times;</button>
                                </span>
                            </template>
                            <span x-show="items.length === 0" class="text-xs text-slate-400 italic">Nenhum item adicionado.</span>
                        </div>
                        <div class="flex gap-2">
                            <input type="text" x-model="input" @keydown.enter.prevent="add()"
                                   placeholder="Ex: EUR, USD…"
                                   class="flex-1 px-3 py-1.5 text-sm bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 uppercase">
                            <button type="button" @click="add()"
                                    class="px-4 py-1.5 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium">
                                Adicionar
                            </button>
                        </div>
                        <input type="hidden" name="settings[{{ $setting->key }}]" :value="JSON.stringify(items)">
                    </div>

                    @elseif ($setting->type === 'timezone')
                    <label class="{{ $lc }}">{{ $setting->label }}</label>
                    <select name="settings[{{ $setting->key }}]" class="{{ $ic }}">
                        @foreach (\DateTimeZone::listIdentifiers() as $tz)
                            <option value="{{ $tz }}" @selected($setting->value === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>

                    @elseif ($setting->type === 'textarea')
                    <label class="{{ $lc }}">{{ $setting->label }}</label>
                    <textarea name="settings[{{ $setting->key }}]" rows="3"
                              class="{{ $ic }} resize-none">{{ $setting->value }}</textarea>

                    @elseif ($setting->type === 'secret')
                    <label class="{{ $lc }}">{{ $setting->label }}</label>
                    <div x-data="{ show: false }" class="relative">
                        <input :type="show ? 'text' : 'password'"
                               name="settings[{{ $setting->key }}]"
                               value="{{ $setting->value }}" autocomplete="new-password"
                               class="{{ $ic }} font-mono pr-10">
                        <button type="button" @click="show = !show"
                                class="absolute inset-y-0 right-3 flex items-center text-slate-400 hover:text-slate-600">
                            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>

                    @elseif ($setting->type === 'number')
                    <label class="{{ $lc }}">{{ $setting->label }}</label>
                    <input type="number" name="settings[{{ $setting->key }}]"
                           value="{{ $setting->value }}" min="1" class="{{ $ic }}">

                    @else
                    <label class="{{ $lc }}">{{ $setting->label }}</label>
                    <input type="{{ $setting->type }}" name="settings[{{ $setting->key }}]"
                           value="{{ $setting->value }}" class="{{ $ic }}">
                    @endif

                </div>
                @endforeach
            </div>

        </div>
        @endforeach

    </div>
    @endforeach

    @can('settings.edit')
    <div class="mt-6">
        <button type="submit"
                class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
            Guardar Configurações
        </button>
    </div>
    @endcan

</form>
@endsection
