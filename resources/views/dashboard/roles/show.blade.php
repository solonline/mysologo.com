@extends('layouts.dashboard')

@section('title', $role->name)
@section('breadcrumb', 'Roles')

@section('page-header')
<div class="flex items-center gap-3">
    <a href="{{ route('dashboard.roles') }}"
       class="p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ $role->name }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $role->permissions->count() }} permissões · {{ $users->total() }} utilizadores</p>
    </div>
    <div class="ml-auto">
        @can('roles.edit')
        <a href="{{ route('dashboard.roles.edit', $role) }}"
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

    {{-- Permissões --}}
    <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Permissões</h2>
        </div>
        <div class="px-6 py-5">
            @php
                $grouped = $role->permissions->groupBy(fn ($p) => explode('.', $p->name)[0]);
            @endphp
            @forelse ($grouped as $module => $perms)
                <div class="mb-4 last:mb-0">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-2">{{ $module }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($perms as $perm)
                            <span class="px-2.5 py-1 text-xs font-medium bg-slate-100 text-slate-700 rounded-lg border border-slate-200">
                                {{ $perm->label ?: $perm->name }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">Nenhuma permissão atribuída.</p>
            @endforelse
        </div>
    </div>

    {{-- Utilizadores com esta role --}}
    <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Utilizadores</h2>
        </div>
        @if ($users->isEmpty())
            <div class="px-6 py-8 text-center text-sm text-slate-400">
                Nenhum utilizador com esta role.
            </div>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <li class="px-6 py-3.5 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-bold flex-shrink-0">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-800 truncate">{{ $user->name }}</p>
                            <p class="text-xs text-slate-400 truncate">{{ $user->email }}</p>
                        </div>
                        <a href="{{ route('dashboard.users.show', $user) }}"
                           class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                            Ver
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($users->hasPages())
                <div class="px-6 py-4 border-t border-slate-100">
                    {{ $users->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- Zona de perigo --}}
    @can('roles.delete')
    <div class="bg-white rounded-2xl border border-red-200/60 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-red-100">
            <h2 class="text-sm font-semibold text-red-600 uppercase tracking-wider">Zona de Perigo</h2>
        </div>
        <div class="px-6 py-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-800">Remover role</p>
                <p class="text-xs text-slate-400 mt-0.5">Esta ação é irreversível.</p>
            </div>
            <form method="POST" action="{{ route('dashboard.roles.destroy', $role) }}"
                  onsubmit="return confirm('Tem certeza que deseja remover esta role?')">
                @csrf @method('DELETE')
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
