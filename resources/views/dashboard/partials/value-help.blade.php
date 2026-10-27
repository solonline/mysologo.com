<div class="relative shrink-0" x-data="{ helpOpen: false }" @click.stop @click.outside="helpOpen = false">
    <span role="button"
          tabindex="0"
          @click.stop="helpOpen = !helpOpen"
          @keydown.enter.prevent.stop="helpOpen = !helpOpen"
          @keydown.space.prevent.stop="helpOpen = !helpOpen"
          :aria-expanded="helpOpen.toString()"
          aria-label="De onde vêm estes valores"
          class="inline-flex h-5 w-5 cursor-pointer items-center justify-center rounded-full border border-slate-200 bg-white text-[11px] font-bold leading-none text-slate-400 hover:border-slate-300 hover:text-slate-600">
        ?
    </span>
    <div x-show="helpOpen"
         x-cloak
         x-transition
         class="absolute right-0 top-7 z-30 rounded-xl border border-slate-200 bg-white p-3 text-left shadow-lg"
         style="width: 36rem; max-width: calc(100vw - 2rem);">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">De onde vêm</p>
        <dl class="mt-2 space-y-2">
            @foreach ($items as [$term, $text])
            <div>
                <dt class="text-xs font-semibold text-slate-800">{{ $term }}</dt>
                <dd class="text-xs text-slate-500">{{ $text }}</dd>
            </div>
            @endforeach
        </dl>
    </div>
</div>
