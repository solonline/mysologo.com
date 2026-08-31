@extends('layouts.dashboard')

@section('title', 'Novo Cliente')
@section('breadcrumb', 'Clientes')

@section('page-header')
<div class="flex items-center gap-3">
    <a href="{{ route('dashboard.customers') }}"
       class="p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Novo Cliente</h1>
        <p class="mt-1 text-sm text-slate-500">Preencha os dados para criar um novo cliente. Será enviado um email de verificação.</p>
    </div>
</div>
@endsection

@section('content')

<div class="max-w-2xl">
    <form method="POST" action="{{ route('dashboard.customers.store') }}"
          class="bg-white rounded-2xl border border-slate-200/60 shadow-sm divide-y divide-slate-100">

        @csrf

        <div class="px-6 py-5 space-y-5">
            <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Informações Pessoais</h2>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Nome completo <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full px-3.5 py-2.5 text-sm border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition
                              {{ $errors->has('name') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" value="{{ old('username') }}" required
                       pattern="[a-zA-Z0-9_]+" maxlength="40" placeholder="joao_silva"
                       class="w-full px-3.5 py-2.5 text-sm border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition
                              {{ $errors->has('username') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                @error('username') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full px-3.5 py-2.5 text-sm border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition
                              {{ $errors->has('email') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                @error('email') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Telefone <span class="text-red-500">*</span></label>
                <input type="text" name="phone" value="{{ old('phone') }}" required
                       inputmode="numeric" pattern="[0-9]+" maxlength="20" placeholder="351912345678"
                       class="w-full px-3.5 py-2.5 text-sm border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition
                              {{ $errors->has('phone') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                @error('phone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="px-6 py-5 space-y-5">
            <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Conta</h2>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Tipo <span class="text-red-500">*</span></label>
                <select name="type" required
                        class="w-full px-3.5 py-2.5 text-sm border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 text-slate-700 transition
                               {{ $errors->has('type') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                    <option value="retail" {{ old('type', 'retail') === 'retail' ? 'selected' : '' }}>Retail</option>
                    <option value="corporate" {{ old('type') === 'corporate' ? 'selected' : '' }}>Corporate</option>
                </select>
                @error('type') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Idioma <span class="text-red-500">*</span></label>
                    <select name="language" required
                            class="w-full px-3.5 py-2.5 text-sm border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 text-slate-700 transition
                                   {{ $errors->has('language') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                        @foreach (['pt' => 'Português', 'en' => 'English', 'es' => 'Español', 'fr' => 'Français'] as $code => $label)
                            <option value="{{ $code }}" {{ old('language', 'pt') === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('language') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Moeda <span class="text-red-500">*</span></label>
                    <input type="text" name="currency" value="{{ old('currency', 'EUR') }}" required maxlength="3"
                           class="w-full px-3.5 py-2.5 text-sm border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition uppercase
                                  {{ $errors->has('currency') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                    @error('currency') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="px-6 py-5 space-y-5">
            <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Acesso</h2>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Senha <span class="text-red-500">*</span></label>
                <input type="password" name="password" required
                       class="w-full px-3.5 py-2.5 text-sm border rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition
                              {{ $errors->has('password') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                @error('password') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirmar senha <span class="text-red-500">*</span></label>
                <input type="password" name="password_confirmation" required
                       class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition">
            </div>
        </div>

        <div class="px-6 py-4 flex items-center justify-end gap-3">
            <a href="{{ route('dashboard.customers') }}"
               class="px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition">
                Cancelar
            </a>
            <button type="submit"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
                Criar Cliente
            </button>
        </div>

    </form>
</div>

@endsection
