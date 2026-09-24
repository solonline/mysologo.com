@extends('layouts.dashboard')

@section('title', 'Cargos')
@section('breadcrumb', 'Cargos')

@section('page-header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Cargos</h1>
        <p class="mt-1 text-sm text-slate-500">Gerir cargos e permissões do sistema.</p>
    </div>
    @can('roles.create')
    <a href="{{ route('dashboard.roles.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Novo Cargo
    </a>
    @endcan
</div>
@endsection

@section('content')

@if (session('success'))
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl">
        {{ session('success') }}
    </div>
@endif

{{-- Filtro --}}
<form method="GET" class="mb-4 flex gap-3">
    <input type="text" name="search" value="{{ request('search') }}"
           placeholder="Pesquisar cargo..."
           class="flex-1 max-w-xs px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
    <button type="submit"
            class="px-4 py-2 bg-slate-800 text-white text-sm font-medium rounded-xl hover:bg-slate-700 transition">
        Filtrar
    </button>
    @if(request('search'))
        <a href="{{ route('dashboard.roles') }}"
           class="px-4 py-2 text-sm text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition">
            Limpar
        </a>
    @endif
</form>

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider">Cargo</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider">Permissões</th>
                <th class="px-5 py-3.5 text-left font-semibold text-slate-500 text-xs uppercase tracking-wider">Utilizadores</th>
                <th class="px-5 py-3.5"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($roles as $role)
            <tr class="hover:bg-slate-50/50 group transition-colors">
                <td class="px-5 py-4">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        {{ $role->name }}
                    </span>
                </td>
                <td class="px-5 py-4 text-slate-500">
                    {{ $role->permissions_count }}
                </td>
                <td class="px-5 py-4 text-slate-500">
                    {{ $role->users_count }}
                </td>
                <td class="px-5 py-4 text-right">
                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <a href="{{ route('dashboard.roles.show', $role) }}"
                           class="px-3 py-1.5 text-xs font-medium text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition">Ver</a>
                        @can('roles.edit')
                        <a href="{{ route('dashboard.roles.edit', $role) }}"
                           class="px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">Editar</a>
                        @endcan
                        @can('roles.delete')
                        <form method="POST" action="{{ route('dashboard.roles.destroy', $role) }}"
                              onsubmit="return confirm('Remover a role \'{{ $role->name }}\'?')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition">
                                Remover
                            </button>
                        </form>
                        @endcan
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="px-5 py-12 text-center text-slate-400 text-sm">
                    Nenhuma role encontrada.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if ($roles->hasPages())
        <div class="px-5 py-4 border-t border-slate-100">
            {{ $roles->links() }}
        </div>
    @endif
</div>

@endsection
