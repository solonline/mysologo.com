@extends('layouts.dashboard')

@section('title', 'Editar Role')
@section('breadcrumb', 'Roles')

@section('page-header')
<div class="flex items-center gap-3">
    <a href="{{ route('dashboard.roles') }}"
       class="p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <h1 class="text-2xl font-bold text-slate-900">Editar Role — <span class="text-indigo-600">{{ $role->name }}</span></h1>
</div>
@endsection

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('dashboard.roles.update', $role) }}" class="space-y-4">
        @csrf
        @method('PUT')

        {{-- Nome --}}
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Identificação</h2>
            </div>
            <div class="px-6 py-5">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                    Nome do Cargo <span class="text-red-400">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $role->name) }}"
                       class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('name') border-red-400 @enderror">
                @error('name')
                    <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Permissões --}}
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Permissões</h2>
            </div>
            <div class="px-6 py-5 space-y-5">
                @foreach ($permissions as $module => $perms)
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest">{{ $module }}</p>
                            {{-- select all module --}}
                            <button type="button"
                                    onclick="toggleModule(this, '{{ $module }}')"
                                    class="text-xs text-indigo-500 hover:text-indigo-700 transition">
                                Selecionar todos
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-3" data-module="{{ $module }}">
                            @foreach ($perms as $perm)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="permissions[]" value="{{ $perm->name }}"
                                           {{ in_array($perm->name, old('permissions', $rolePermissions)) ? 'checked' : '' }}
                                           class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm text-slate-700">{{ $perm->label ?: $perm->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
                Guardar Alterações
            </button>
            <a href="{{ route('dashboard.roles') }}"
               class="px-5 py-2.5 text-sm text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition">
                Cancelar
            </a>
        </div>
    </form>
</div>

@push('scripts')
<script>
function toggleModule(btn, module) {
    const container = document.querySelector(`[data-module="${module}"]`);
    const checkboxes = container.querySelectorAll('input[type="checkbox"]');
    const allChecked = [...checkboxes].every(c => c.checked);
    checkboxes.forEach(c => c.checked = !allChecked);
    btn.textContent = allChecked ? 'Selecionar todos' : 'Desselecionar todos';
}
</script>
@endpush
@endsection
