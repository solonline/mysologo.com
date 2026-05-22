@extends('layouts.dashboard')

@section('title', 'Contas a Pagar')
@section('breadcrumb', 'Contas a Pagar')

@php
    $statusLabels = [
        'pending' => 'Pendente',
        'paid' => 'Paga',
        'cancelled' => 'Cancelada',
        'overdue' => 'Vencida',
    ];

    $badgeClasses = [
        'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
        'paid' => 'bg-green-50 text-green-700 border-green-200',
        'cancelled' => 'bg-slate-100 text-slate-500 border-slate-200',
        'overdue' => 'bg-red-50 text-red-700 border-red-200',
    ];
@endphp

@section('page-header')
<div class="flex items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Contas a Pagar</h1>
        <p class="mt-1 text-sm text-slate-500">Gerir despesas, faturas e recorrências financeiras.</p>
    </div>
    @can('accounts_payable.create')
    <a href="{{ route('dashboard.accounts-payable.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nova Conta
    </a>
    @endcan
</div>
@endsection

@section('content')
<form method="GET" class="mb-4 grid grid-cols-1 md:grid-cols-6 gap-3">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Pesquisar..."
           class="px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 md:col-span-2">
    <input type="text" name="supplier" value="{{ request('supplier') }}" placeholder="Fornecedor"
           class="px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
    <select name="category"
            class="px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
        <option value="">Categorias</option>
        @foreach ($categories as $category)
            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
        @endforeach
    </select>
    <select name="status"
            class="px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
        <option value="">Estados</option>
        @foreach ($statuses as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $statusLabels[$status] }}</option>
        @endforeach
    </select>
    <select name="currency"
            class="px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
        <option value="">Moedas</option>
        @foreach ($currencies as $currency)
            <option value="{{ $currency }}" @selected(request('currency') === $currency)>{{ $currency }}</option>
        @endforeach
    </select>
    <input type="date" name="due_from" value="{{ request('due_from') }}"
           class="px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
    <input type="date" name="due_to" value="{{ request('due_to') }}"
           class="px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
    <label class="inline-flex items-center gap-2 px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm text-slate-600">
        <input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))
               class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
        Vencidas
    </label>
    <div class="flex gap-2 md:col-span-3">
        <button type="submit" class="px-4 py-2 bg-slate-800 text-white text-sm font-medium rounded-xl hover:bg-slate-700 transition">
            Filtrar
        </button>
        @if(request()->query())
            <a href="{{ route('dashboard.accounts-payable') }}"
               class="px-4 py-2 text-sm text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition">
                Limpar
            </a>
        @endif
    </div>
</form>

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider">Conta</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider hidden md:table-cell">Fornecedor</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider hidden lg:table-cell">Vencimento</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider">Valor</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider">Estado</th>
                <th class="px-5 py-3.5"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($accounts as $account)
            @php $displayStatus = $account->displayStatus(); @endphp
            <tr class="hover:bg-slate-50/50 group transition-colors">
                <td class="px-5 py-4">
                    <p class="font-medium text-slate-800">{{ $account->description }}</p>
                    <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                        @if($account->document_number)
                            <span>{{ $account->document_number }}</span>
                        @endif
                        @if($account->category)
                            <span>{{ $account->category }}</span>
                        @endif
                        @if($account->is_recurring)
                            <span class="text-indigo-500">#{{ $account->occurrence_index }}/{{ $account->occurrence_total }}</span>
                        @endif
                        @if($account->attachments_count)
                            <span>{{ $account->attachments_count }} anexo(s)</span>
                        @endif
                    </div>
                </td>
                <td class="px-5 py-4 text-slate-600 hidden md:table-cell">{{ $account->supplier }}</td>
                <td class="px-5 py-4 text-slate-600 hidden lg:table-cell">{{ $account->due_date->format('d/m/Y') }}</td>
                <td class="px-5 py-4 font-medium text-slate-800">{{ number_format((float) $account->amount, 2, ',', '.') }} {{ $account->currency }}</td>
                <td class="px-5 py-4">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $badgeClasses[$displayStatus] }}">
                        {{ $statusLabels[$displayStatus] }}
                    </span>
                </td>
                <td class="px-5 py-4 text-right">
                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        @can('accounts_payable.pay')
                        @if($account->status === 'pending')
                        <form method="POST" action="{{ route('dashboard.accounts-payable.paid', $account) }}">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 text-xs font-medium text-green-600 bg-green-50 rounded-lg hover:bg-green-100 transition">
                                Pagar
                            </button>
                        </form>
                        @endif
                        @endcan
                        @can('accounts_payable.edit')
                        <a href="{{ route('dashboard.accounts-payable.edit', $account) }}"
                           class="px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">Editar</a>
                        @if($account->status !== 'cancelled')
                        <form method="POST" action="{{ route('dashboard.accounts-payable.cancel', $account) }}"
                              onsubmit="return confirm('Cancelar esta conta a pagar?')">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 text-xs font-medium text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition">
                                Cancelar
                            </button>
                        </form>
                        @endif
                        @endcan
                        @can('accounts_payable.delete')
                        <form method="POST" action="{{ route('dashboard.accounts-payable.destroy', $account) }}"
                              onsubmit="return confirm('Remover esta conta a pagar?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition">
                                Remover
                            </button>
                        </form>
                        @endcan
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-5 py-12 text-center text-slate-400 text-sm">
                    Nenhuma conta a pagar encontrada.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if ($accounts->hasPages())
        <div class="px-5 py-4 border-t border-slate-100">
            {{ $accounts->links() }}
        </div>
    @endif
</div>
@endsection
