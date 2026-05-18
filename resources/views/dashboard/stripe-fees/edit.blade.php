@extends('layouts.dashboard')

@section('title', 'Editar Taxa Stripe')
@section('breadcrumb', 'Taxas Stripe')

@section('page-header')
<div class="flex items-center gap-3">
    <a href="{{ route('dashboard.stripe-fees') }}"
       class="p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <h1 class="text-2xl font-bold text-slate-900">Editar — {{ $stripeFee->label }}</h1>
</div>
@endsection

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('dashboard.stripe-fees.update', $stripeFee) }}" class="space-y-4">
        @csrf
        @method('PUT')

        @include('dashboard.stripe-fees._form', ['fee' => $stripeFee])

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
                Guardar Alterações
            </button>
            <a href="{{ route('dashboard.stripe-fees') }}"
               class="px-5 py-2.5 text-sm text-red-600 bg-red-50 rounded-xl hover:bg-red-100 transition">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
