@extends('layouts.dashboard')

@section('title', 'Editar Utilizador')
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
        <h1 class="text-2xl font-bold text-slate-900">Editar Utilizador</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $user->name }}</p>
    </div>
</div>
@endsection

@section('content')

<div class="max-w-2xl">
    <form method="POST" action="{{ route('dashboard.users.update', $user) }}"
          class="bg-white rounded-2xl border border-slate-200/60 shadow-sm divide-y divide-slate-100">

        @csrf
        @method('PUT')

        <div class="px-6 py-5 space-y-5">
            <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Informações Pessoais</h2>

            {{-- Name --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Nome completo <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}"
                       class="w-full px-3.5 py-2.5 text-sm border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition
                              {{ $errors->has('name') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Username --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" value="{{ old('username', $user->username) }}"
                       class="w-full px-3.5 py-2.5 text-sm border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition
                              {{ $errors->has('username') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                @error('username') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Email --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}"
                       class="w-full px-3.5 py-2.5 text-sm border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition
                              {{ $errors->has('email') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                @error('email') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Phone --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Telefone</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                       class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition">
                @error('phone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="px-6 py-5 space-y-5">
            <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Acesso</h2>

            {{-- Role --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Role</label>
                <select name="role"
                        class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 text-slate-700 transition">
                    <option value="">Sem role</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}"
                            {{ old('role', $user->roles->first()?->name) === $role->name ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
                @error('role') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Password --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">
                    Nova Senha
                    <span class="text-xs text-slate-400 font-normal">(deixe em branco para manter)</span>
                </label>
                <input type="password" name="password"
                       class="w-full px-3.5 py-2.5 text-sm border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition
                              {{ $errors->has('password') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                @error('password') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Password Confirmation --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirmar Nova Senha</label>
                <input type="password" name="password_confirmation"
                       class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition">
            </div>
        </div>

        <div class="px-6 py-4 flex items-center justify-end gap-3">
            <a href="{{ route('dashboard.users') }}"
               class="px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition">
                Cancelar
            </a>
            <button type="submit"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
                Salvar Alterações
            </button>
        </div>

    </form>
</div>

@endsection
