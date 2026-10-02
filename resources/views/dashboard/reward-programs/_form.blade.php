@php $program = $program ?? null; @endphp

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Programa</h2>
    </div>
    <div class="px-6 py-5 space-y-5">
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Código <span class="text-red-400">*</span>
            </label>
            @if ($program)
                <input type="text" value="{{ $program->slug }}" disabled
                       class="w-full px-3 py-2 text-sm bg-slate-100 border border-slate-200 rounded-xl text-slate-500 font-mono">
                <p class="mt-1.5 text-xs text-slate-400">O código não pode ser alterado depois de criado.</p>
            @else
                <input type="text" name="slug"
                       value="{{ old('slug') }}"
                       placeholder="ex: referral, welcome"
                       class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('slug') border-red-400 @enderror">
                <p class="mt-1.5 text-xs text-slate-400">Minúsculas, números e underscore. Usado internamente e na API.</p>
                @error('slug')
                    <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                @enderror
            @endif
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Nome <span class="text-red-400">*</span>
            </label>
            <input type="text" name="name"
                   value="{{ old('name', $program?->name) }}"
                   placeholder="Nome visível na app"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('name') border-red-400 @enderror">
            @error('name')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Descrição / termos
            </label>
            <textarea name="description" rows="5"
                      placeholder="Detalhes, condições e termos do programa."
                      class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('description') border-red-400 @enderror">{{ old('description', $program?->description) }}</textarea>
            @error('description')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Validade e estado</h2>
    </div>
    <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Válido de
            </label>
            <input type="datetime-local" name="valid_from"
                   value="{{ old('valid_from', $program?->valid_from?->format('Y-m-d\TH:i')) }}"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('valid_from') border-red-400 @enderror">
            @error('valid_from')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Válido até
            </label>
            <input type="datetime-local" name="valid_until"
                   value="{{ old('valid_until', $program?->valid_until?->format('Y-m-d\TH:i')) }}"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('valid_until') border-red-400 @enderror">
            <p class="mt-1.5 text-xs text-slate-400">Vazio = sem prazo.</p>
            @error('valid_until')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-2 flex items-center gap-3 pt-1">
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       {{ filter_var(old('is_active', $program?->is_active ?? true), FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                       class="sr-only peer">
                <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                <span class="ml-2 text-sm font-medium text-slate-700">Programa activo</span>
            </label>
        </div>
    </div>
</div>
