@extends('layouts.dashboard')

@section('title', $user->name)
@section('breadcrumb', 'Utilizadores')

@section('page-header')
<div class="flex items-center gap-3">
    <a href="{{ route('dashboard.users') }}"
       class="p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ $user->name }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $user->email }}</p>
    </div>
    <div class="ml-auto">
        @can('users.edit')
        <a href="{{ route('dashboard.users.edit', $user) }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
            </svg>
            Editar
        </a>
        @endcan
    </div>
</div>
@endsection

@section('content')

<div class="max-w-2xl space-y-4">

    {{-- Profile card --}}
    <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Informações Pessoais</h2>
        </div>
        <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-medium mb-1">Nome</p>
                <p class="text-sm font-semibold text-slate-900">{{ $user->name }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-medium mb-1">Username</p>
                <p class="text-sm text-slate-700">{{ $user->username }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-medium mb-1">Email</p>
                <p class="text-sm text-slate-700">{{ $user->email }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-medium mb-1">Telefone</p>
                <p class="text-sm text-slate-700">{{ $user->phone ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-medium mb-1">Criado em</p>
                <p class="text-sm text-slate-700">{{ $user->created_at?->format('d/m/Y H:i') }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-medium mb-1">Última atualização</p>
                <p class="text-sm text-slate-700">{{ $user->updated_at?->format('d/m/Y H:i') }}</p>
            </div>
        </div>
    </div>

    {{-- Roles card --}}
    <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Roles & Permissões</h2>
        </div>
        <div class="px-6 py-5">
            @forelse ($user->roles as $role)
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium border bg-indigo-50 text-indigo-700 border-indigo-200 mr-2 mb-2">
                    {{ $role->name }}
                </span>
            @empty
                <p class="text-sm text-slate-400">Nenhum role atribuído.</p>
            @endforelse
        </div>
    </div>

    {{-- Danger zone --}}
    @can('users.delete')
    <div class="bg-white rounded-2xl border border-red-200/60 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-red-100">
            <h2 class="text-sm font-semibold text-red-600 uppercase tracking-wider">Zona de Perigo</h2>
        </div>
        <div class="px-6 py-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-800">Remover utilizador</p>
                <p class="text-xs text-slate-400 mt-0.5">Esta ação é irreversível.</p>
            </div>
            <form method="POST" action="{{ route('dashboard.users.destroy', $user) }}"
                  onsubmit="return confirm('Tem certeza que deseja remover este utilizador?')">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-xl hover:bg-red-700 transition">
                    Remover
                </button>
            </form>
        </div>
    </div>
    @endcan

</div>

@endsection
