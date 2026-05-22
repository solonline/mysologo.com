@extends('layouts.dashboard')

@section('title', 'Editar Conta a Pagar')
@section('breadcrumb', 'Contas a Pagar')

@section('page-header')
<div class="flex items-center gap-3">
    <a href="{{ route('dashboard.accounts-payable') }}"
       class="p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <h1 class="text-2xl font-bold text-slate-900">Editar - {{ $account->description }}</h1>
</div>
@endsection

@section('content')
<div class="max-w-4xl space-y-4">
    <form method="POST" action="{{ route('dashboard.accounts-payable.update', $account) }}" class="space-y-4">
        @csrf
        @method('PUT')

        @include('dashboard.accounts-payable._form')

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
                Guardar Alterações
            </button>
            <a href="{{ route('dashboard.accounts-payable') }}"
               class="px-5 py-2.5 text-sm text-red-600 bg-red-50 rounded-xl hover:bg-red-100 transition">
                Cancelar
            </a>
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Anexos</h2>
        </div>
        <div class="px-6 py-5 space-y-4">
            @can('accounts_payable.edit')
            <form method="POST" action="{{ route('dashboard.accounts-payable.attachments.store', $account) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp"
                       class="block text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-600 hover:file:bg-indigo-100">
                <button type="submit"
                        class="px-4 py-2 bg-slate-800 text-white text-sm font-medium rounded-xl hover:bg-slate-700 transition">
                    Enviar
                </button>
                @error('attachments') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                @error('attachments.*') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </form>
            @endcan

            <div class="divide-y divide-slate-100">
                @forelse($account->attachments as $attachment)
                    <div class="flex items-center justify-between gap-4 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('dashboard.accounts-payable.attachments.download', [$account, $attachment]) }}"
                               class="font-medium text-sm text-indigo-600 hover:text-indigo-700 truncate">
                                {{ $attachment->original_name }}
                            </a>
                            <p class="text-xs text-slate-400">{{ number_format($attachment->size / 1024, 1, ',', '.') }} KB</p>
                        </div>
                        @can('accounts_payable.edit')
                        <form method="POST" action="{{ route('dashboard.accounts-payable.attachments.destroy', [$account, $attachment]) }}"
                              onsubmit="return confirm('Remover este anexo?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition">
                                Remover
                            </button>
                        </form>
                        @endcan
                    </div>
                @empty
                    <p class="py-4 text-sm text-slate-400">Nenhum anexo enviado.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
