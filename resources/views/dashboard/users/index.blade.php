@extends('layouts.dashboard')

@section('title', 'Utilizadores')
@section('breadcrumb', 'Utilizadores')

@section('page-header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Utilizadores</h1>
        <p class="mt-1 text-sm text-slate-500">Gerencie os utilizadores com acesso ao dashboard.</p>
    </div>
    @can('users.create')
    <a href="{{ route('dashboard.users.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Novo Utilizador
    </a>
    @endcan
</div>
@endsection

@section('content')

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">

    {{-- Filters --}}
    <div class="px-5 py-4 border-b border-slate-100">
        <form method="GET" action="{{ route('dashboard.users') }}" class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-48">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Buscar por nome, email ou username..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition">
            </div>

            <select name="role"
                    class="py-2 px-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 text-slate-700 transition">
                <option value="">Todos os roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->name }}" {{ request('role') === $role->name ? 'selected' : '' }}>
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>

            <button type="submit"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
                Filtrar
            </button>

            @if (request()->hasAny(['search', 'role']))
            <a href="{{ route('dashboard.users') }}"
               class="px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition">
                Limpar
            </a>
            @endif
        </form>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-100">
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-5 py-3">Utilizador</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 hidden sm:table-cell">Username</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 hidden md:table-cell">Telefone</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Role</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 hidden md:table-cell">Criado em</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                <tr class="hover:bg-slate-50/50 transition-colors group">
                    {{-- Utilizador --}}
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500
                                        flex items-center justify-center text-white text-xs font-bold flex-shrink-0 shadow-sm">
                                {{ strtoupper(substr($user->name ?? 'U', 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ $user->name }}</p>
                                <p class="text-xs text-slate-400 truncate">{{ $user->email }}</p>
                            </div>
                        </div>
                    </td>
                    {{-- Username --}}
                    <td class="px-4 py-3.5 hidden sm:table-cell">
                        <span class="text-sm text-slate-600">{{ $user->username }}</span>
                    </td>
                    {{-- Telefone --}}
                    <td class="px-4 py-3.5 hidden md:table-cell">
                        <span class="text-sm text-slate-600">{{ $user->phone ?? '—' }}</span>
                    </td>
                    {{-- Role --}}
                    <td class="px-4 py-3.5">
                        @forelse ($user->roles as $role)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-indigo-50 text-indigo-700 border-indigo-200">
                                {{ $role->name }}
                            </span>
                        @empty
                            <span class="text-xs text-slate-400">—</span>
                        @endforelse
                    </td>
                    {{-- Data --}}
                    <td class="px-4 py-3.5 hidden md:table-cell">
                        <span class="text-sm text-slate-500">{{ $user->created_at?->format('d/m/Y') }}</span>
                    </td>
                    {{-- Ações --}}
                    <td class="px-4 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="{{ route('dashboard.users.show', $user) }}"
                               class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 border border-slate-200 rounded-lg transition-colors">
                                Ver
                            </a>
                            @can('users.edit')
                            <a href="{{ route('dashboard.users.edit', $user) }}"
                               class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg transition-colors">
                                Editar
                            </a>
                            @endcan
                            @can('users.delete')
                            <form method="POST" action="{{ route('dashboard.users.destroy', $user) }}"
                                  onsubmit="return confirm('Tem certeza que deseja remover este utilizador?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg transition-colors">
                                    Remover
                                </button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center">
                                <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-700">Nenhum utilizador encontrado</p>
                                <p class="text-xs text-slate-400 mt-1">Tente ajustar os filtros ou crie um novo utilizador.</p>
                            </div>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($users->hasPages())
    <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
        <p class="text-xs text-slate-500">
            Mostrando <span class="font-semibold text-slate-700">{{ $users->firstItem() }}</span>–<span class="font-semibold text-slate-700">{{ $users->lastItem() }}</span>
            de <span class="font-semibold text-slate-700">{{ $users->total() }}</span> utilizadores
        </p>
        <div class="flex items-center gap-1">
            @if ($users->onFirstPage())
                <span class="px-3 py-1.5 text-xs text-slate-300 bg-slate-50 border border-slate-200 rounded-lg cursor-not-allowed">← Anterior</span>
            @else
                <a href="{{ $users->previousPageUrl() }}"
                   class="px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">← Anterior</a>
            @endif
            @foreach ($users->getUrlRange(max(1, $users->currentPage() - 2), min($users->lastPage(), $users->currentPage() + 2)) as $page => $url)
                @if ($page === $users->currentPage())
                    <span class="px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 border border-indigo-600 rounded-lg">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">{{ $page }}</a>
                @endif
            @endforeach
            @if ($users->hasMorePages())
                <a href="{{ $users->nextPageUrl() }}"
                   class="px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">Próximo →</a>
            @else
                <span class="px-3 py-1.5 text-xs text-slate-300 bg-slate-50 border border-slate-200 rounded-lg cursor-not-allowed">Próximo →</span>
            @endif
        </div>
    </div>
    @endif

</div>

@endsection
