@extends('layouts.dashboard')

@section('title', 'Detalhe do Registo')
@section('breadcrumb', 'Auditoria')

@section('page-header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Detalhe do Registo</h1>
        <p class="mt-1 text-sm text-slate-500">Informação completa sobre a alteração registada.</p>
    </div>
    <a href="{{ route('dashboard.audit-logs') }}"
       class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Voltar
    </a>
</div>
@endsection

@section('content')

@php
    $event = $activity->event ?? $activity->description ?? 'updated';

    $eventConfig = match($event) {
        'created' => ['label' => 'Criado',     'bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'dot' => 'bg-emerald-500'],
        'updated' => ['label' => 'Atualizado', 'bg' => 'bg-amber-50',   'text' => 'text-amber-700',   'border' => 'border-amber-200',   'dot' => 'bg-amber-500'],
        'deleted' => ['label' => 'Removido',   'bg' => 'bg-red-50',     'text' => 'text-red-700',     'border' => 'border-red-200',     'dot' => 'bg-red-500'],
        default   => ['label' => ucfirst($event), 'bg' => 'bg-slate-50', 'text' => 'text-slate-600', 'border' => 'border-slate-200', 'dot' => 'bg-slate-400'],
    };

    $subjectLabels = [
        'App\\Models\\Customer'    => 'Cliente',
        'App\\Models\\Transaction' => 'Transação',
    ];

    $logLabels = [
        'customers'    => 'Clientes',
        'transactions' => 'Transações',
        'default'      => 'Sistema',
    ];

    $subjectLabel = $subjectLabels[$activity->subject_type ?? ''] ?? class_basename($activity->subject_type ?? 'Registo');
    $logLabel     = $logLabels[$activity->log_name ?? ''] ?? ucfirst($activity->log_name ?? 'sistema');
    $causerName   = $activity->causer?->name ?? 'Sistema';

    $attributes = collect($activity->properties['attributes'] ?? []);
    $old        = collect($activity->properties['old'] ?? []);

    // Para created: só attributes. Para deleted: só old. Para updated: merge das chaves.
    $fields = match($event) {
        'created' => $attributes->keys(),
        'deleted' => $old->keys(),
        default   => $attributes->keys()->merge($old->keys())->unique(),
    };
@endphp

<div class="space-y-5">

    {{-- Summary card --}}
    <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6">
        <div class="grid grid-cols-2 gap-x-8 gap-y-5 sm:grid-cols-4">

            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Evento</p>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border
                             {{ $eventConfig['bg'] }} {{ $eventConfig['text'] }} {{ $eventConfig['border'] }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $eventConfig['dot'] }}"></span>
                    {{ $eventConfig['label'] }}
                </span>
            </div>

            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Entidade</p>
                <p class="text-sm font-medium text-slate-900">
                    {{ $subjectLabel }}
                    @if ($activity->subject_id)
                        <span class="text-slate-400 font-normal">#{{ $activity->subject_id }}</span>
                    @endif
                </p>
            </div>

            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Alterado por</p>
                <div class="flex items-center gap-2">
                    @if ($activity->causer)
                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0"
                             style="background: linear-gradient(135deg,#7c3aed,#a855f7);">
                            {{ strtoupper(substr($causerName, 0, 2)) }}
                        </div>
                        <span class="text-sm text-slate-700">{{ $causerName }}</span>
                    @else
                        <span class="text-sm text-slate-400 italic">Sistema</span>
                    @endif
                </div>
            </div>

            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Data/Hora</p>
                <p class="text-sm text-slate-700">{{ $activity->created_at?->format('d/m/Y \à\s H:i:s') }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Canal</p>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
                    {{ $logLabel }}
                </span>
            </div>

            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">ID do registo</p>
                <p class="text-sm text-slate-500 font-mono">{{ $activity->id }}</p>
            </div>

            @if ($activity->batch_uuid)
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Batch UUID</p>
                <p class="text-xs text-slate-500 font-mono truncate" title="{{ $activity->batch_uuid }}">{{ $activity->batch_uuid }}</p>
            </div>
            @endif

        </div>
    </div>

    {{-- Properties card --}}
    @if ($fields->isNotEmpty())
    <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">

        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-800">
                @if ($event === 'created')
                    Valores registados
                @elseif ($event === 'deleted')
                    Valores removidos
                @else
                    Alterações realizadas
                @endif
            </h2>
            @if ($event === 'updated')
            <p class="text-xs text-slate-400 mt-0.5">Campos que tiveram o valor alterado nesta operação.</p>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-100">
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-5 py-3 w-1/3">Campo</th>
                        @if ($event === 'updated')
                            <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 w-1/3">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-red-400"></span>
                                    Valor anterior
                                </span>
                            </th>
                            <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 w-1/3">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                    Novo valor
                                </span>
                            </th>
                        @elseif ($event === 'created')
                            <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Valor</th>
                        @else
                            <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Valor removido</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($fields as $field)
                    @php
                        $newVal = $attributes->get($field);
                        $oldVal = $old->get($field);
                        $changed = $event === 'updated' && $newVal !== $oldVal;

                        $formatValue = fn($v) => match(true) {
                            is_null($v)  => '—',
                            is_bool($v)  => $v ? 'sim' : 'não',
                            is_array($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                            default      => (string) $v,
                        };
                    @endphp
                    <tr class="{{ $changed ? 'bg-amber-50/30' : '' }} hover:bg-slate-50/50 transition-colors">
                        <td class="px-5 py-3.5">
                            <code class="text-xs font-mono text-slate-700 bg-slate-100 px-2 py-0.5 rounded">{{ $field }}</code>
                        </td>
                        @if ($event === 'updated')
                            <td class="px-4 py-3.5">
                                <span class="text-sm {{ $changed ? 'text-red-600 line-through' : 'text-slate-500' }}">
                                    {{ $formatValue($oldVal) }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="text-sm {{ $changed ? 'text-emerald-700 font-medium' : 'text-slate-500' }}">
                                    {{ $formatValue($newVal) }}
                                </span>
                            </td>
                        @elseif ($event === 'created')
                            <td class="px-4 py-3.5">
                                <span class="text-sm text-slate-700">{{ $formatValue($newVal) }}</span>
                            </td>
                        @else
                            <td class="px-4 py-3.5">
                                <span class="text-sm text-slate-500 line-through">{{ $formatValue($oldVal) }}</span>
                            </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>
    @else
    <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm px-5 py-10 text-center">
        <p class="text-sm text-slate-400">Nenhuma propriedade registada para este evento.</p>
    </div>
    @endif

</div>

@endsection
