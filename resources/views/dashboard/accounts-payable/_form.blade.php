@php
    $account = $account ?? null;
    $selectedStatus = old('status', $account?->status ?? 'pending');
    $isRecurring = old('is_recurring', $account?->is_recurring ?? false);
@endphp

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Detalhes fiscais</h2>
    </div>
    <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Fornecedor <span class="text-red-400">*</span></label>
            <input type="text" name="supplier" value="{{ old('supplier', $account?->supplier) }}"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('supplier') border-red-400 @enderror">
            @error('supplier') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Descrição <span class="text-red-400">*</span></label>
            <input type="text" name="description" value="{{ old('description', $account?->description) }}"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('description') border-red-400 @enderror">
            @error('description') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Categoria</label>
            <input type="text" name="category" value="{{ old('category', $account?->category) }}"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Documento/Fatura</label>
            <input type="text" name="document_number" value="{{ old('document_number', $account?->document_number) }}"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Data de emissão</label>
            <input type="date" name="issued_at" value="{{ old('issued_at', $account?->issued_at?->format('Y-m-d')) }}"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Vencimento <span class="text-red-400">*</span></label>
            <input type="date" name="due_date" value="{{ old('due_date', $account?->due_date?->format('Y-m-d')) }}"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('due_date') border-red-400 @enderror">
            @error('due_date') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Valor <span class="text-red-400">*</span></label>
            <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount', $account?->amount) }}"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('amount') border-red-400 @enderror">
            @error('amount') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Moeda <span class="text-red-400">*</span></label>
            <select name="currency"
                    class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
                @foreach($currencies as $currency)
                    <option value="{{ $currency }}" @selected(old('currency', $account?->currency ?? 'EUR') === $currency)>{{ $currency }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Estado</label>
            <select name="status"
                    class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
                <option value="pending" @selected($selectedStatus === 'pending')>Pendente</option>
                <option value="paid" @selected($selectedStatus === 'paid')>Paga</option>
                <option value="cancelled" @selected($selectedStatus === 'cancelled')>Cancelada</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Data de pagamento</label>
            <input type="date" name="paid_at" value="{{ old('paid_at', $account?->paid_at?->format('Y-m-d')) }}"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Observações</label>
            <textarea name="notes" rows="3"
                      class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">{{ old('notes', $account?->notes) }}</textarea>
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden" x-data="{ recurring: {{ $isRecurring ? 'true' : 'false' }} }">
    <div class="px-6 py-5 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Recorrência</h2>
    </div>
    <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="sm:col-span-3">
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="hidden" name="is_recurring" value="0">
                <input type="checkbox" name="is_recurring" value="1" x-model="recurring"
                       {{ $isRecurring ? 'checked' : '' }}
                       class="sr-only peer" {{ $account ? 'disabled' : '' }}>
                <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                <span class="ml-2 text-sm font-medium text-slate-700">Conta recorrente</span>
            </label>
            @if($account?->is_recurring)
                <p class="mt-1.5 text-xs text-slate-400">Edição afeta apenas esta ocorrência.</p>
                <input type="hidden" name="is_recurring" value="1">
            @endif
        </div>
        <div x-show="recurring" x-cloak>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Intervalo</label>
            <select name="recurrence_interval"
                    class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
                <option value="">Escolher</option>
                <option value="weekly" @selected(old('recurrence_interval', $account?->recurrence_interval) === 'weekly')>Semanal</option>
                <option value="monthly" @selected(old('recurrence_interval', $account?->recurrence_interval) === 'monthly')>Mensal</option>
                <option value="quarterly" @selected(old('recurrence_interval', $account?->recurrence_interval) === 'quarterly')>Trimestral</option>
                <option value="yearly" @selected(old('recurrence_interval', $account?->recurrence_interval) === 'yearly')>Anual</option>
            </select>
            @error('recurrence_interval') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
        <div x-show="recurring" x-cloak>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Repetir até</label>
            <input type="date" name="recurrence_ends_at" value="{{ old('recurrence_ends_at', $account?->recurrence_ends_at?->format('Y-m-d')) }}"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
            @error('recurrence_ends_at') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
        <div x-show="recurring" x-cloak>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Nº ocorrências</label>
            <input type="number" name="recurrence_occurrences" min="1" max="240" value="{{ old('recurrence_occurrences', $account?->recurrence_occurrences) }}"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400">
            @error('recurrence_occurrences') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
