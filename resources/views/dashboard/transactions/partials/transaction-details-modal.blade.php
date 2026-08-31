{{-- Modal de detalhes (Stripe / Wallester) — Alpine.js --}}
<template x-teleport="body">
<div x-show="showDetailsModal" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-md" @click="showDetailsModal = false"></div>

    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         @click.stop>
        <div class="px-6 py-5 border-b border-slate-100 flex items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-slate-900">Detalhes da transação</h3>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide"
                          :class="details?.kind === 'wallester' ? 'bg-violet-50 text-violet-700' : 'bg-indigo-50 text-indigo-700'"
                          x-text="details?.kind === 'wallester' ? 'Wallester' : 'Stripe'"></span>
                </div>
                <p class="text-xs text-slate-500 mt-1" x-text="details?.subtitle ?? ''"></p>
            </div>
            <button type="button" @click="showDetailsModal = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-6 py-5 max-h-[70vh] overflow-y-auto space-y-5" x-show="details">
            {{-- Montante em destaque --}}
            <div class="p-4 rounded-xl border"
                 :class="details?.isIncoming ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200'">
                <p class="text-xs font-semibold uppercase tracking-wider"
                   :class="details?.isIncoming ? 'text-emerald-700' : 'text-red-700'">Montante</p>
                <p class="text-2xl font-bold mt-1"
                   :class="details?.isIncoming ? 'text-emerald-800' : 'text-red-700'"
                   x-text="details?.amountLabel ?? '—'"></p>
                <p class="text-xs mt-1"
                   :class="details?.isIncoming ? 'text-emerald-600' : 'text-red-600'"
                   x-show="details?.netAmountLabel"
                   x-text="details?.netAmountLabel"></p>
            </div>

            {{-- Campos em grelha --}}
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <template x-for="field in (details?.fields ?? [])" :key="field.label">
                    <div :class="field.full ? 'sm:col-span-2' : ''">
                        <dt class="text-xs font-medium text-slate-500 uppercase tracking-wide" x-text="field.label"></dt>
                        <dd class="mt-1 text-slate-800 break-words">
                            <a x-show="field.link" :href="field.link"
                               class="text-indigo-600 hover:underline font-medium"
                               x-text="field.value"></a>
                            <span x-show="!field.link && field.mono"
                                  class="font-mono text-xs text-slate-700"
                                  x-text="field.value"></span>
                            <span x-show="!field.link && !field.mono" x-text="field.value"></span>
                        </dd>
                    </div>
                </template>
            </dl>

            {{-- Metadata JSON (Wallester) --}}
            <div x-show="details?.metadata" class="pt-2 border-t border-slate-100">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">Metadata</p>
                <pre class="text-[11px] font-mono text-slate-600 bg-slate-50 border border-slate-200 rounded-xl p-3 overflow-x-auto whitespace-pre-wrap"
                     x-text="details?.metadata"></pre>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-3">
            <a x-show="details?.customerUrl"
               :href="details?.customerUrl"
               class="text-sm font-medium text-indigo-600 hover:underline">
                Ver cliente
            </a>
            <button type="button" @click="showDetailsModal = false"
                    class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition">
                Fechar
            </button>
        </div>
    </div>
</div>
</template>
