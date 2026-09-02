@php $news = $news ?? null; @endphp


<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Capa</h2>
        <p class="text-xs text-slate-400 mt-1">JPG, PNG ou WebP. Máximo 5 MB.</p>
    </div>
    <div class="px-6 py-5 space-y-4">
        @if ($news?->image_path)
            <div class="flex items-start gap-4">
                <img src="{{ $news->imageUrl() }}" alt=""
                     class="w-28 h-20 rounded-xl object-cover border border-slate-100">
                <label class="flex items-center gap-2 text-sm text-slate-600 pt-1">
                    <input type="checkbox" name="remove_image" value="1"
                           {{ old('remove_image') ? 'checked' : '' }}
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Remover imagem actual
                </label>
            </div>
        @endif
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp"
               class="block w-full text-sm text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
        @error('image')
            <p class="text-xs text-red-500">{{ $message }}</p>
        @enderror
    </div>
</div>


<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Conteúdo</h2>
    </div>
    <div class="px-6 py-5 space-y-5">
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Título <span class="text-red-400">*</span>
            </label>
            <input type="text" name="title"
                   value="{{ old('title', $news?->title) }}"
                   placeholder="Título da notícia"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('title') border-red-400 @enderror">
            @error('title')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Resumo
            </label>
            <textarea name="excerpt" rows="2"
                      placeholder="Texto curto para a lista no app. Se vazio, é gerado a partir do conteúdo."
                      class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('excerpt') border-red-400 @enderror">{{ old('excerpt', $news?->excerpt) }}</textarea>
            @error('excerpt')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Conteúdo <span class="text-red-400">*</span>
            </label>
            <textarea name="body" id="news-body" rows="10"
                      class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl @error('body') border-red-400 @enderror">{{ old('body', $news?->body) }}</textarea>
            @error('body')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Publicação</h2>
    </div>
    <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Estado <span class="text-red-400">*</span>
            </label>
            <select name="status"
                    class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('status') border-red-400 @enderror">
                <option value="draft" @selected(old('status', $news?->status ?? 'draft') === 'draft')>Rascunho</option>
                <option value="published" @selected(old('status', $news?->status) === 'published')>Publicada</option>
            </select>
            @error('status')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Público <span class="text-red-400">*</span>
            </label>
            <select name="audience"
                    class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('audience') border-red-400 @enderror">
                <option value="all" @selected(old('audience', $news?->audience ?? 'all') === 'all')>Todos</option>
                <option value="retail" @selected(old('audience', $news?->audience) === 'retail')>Retail</option>
                <option value="corporate" @selected(old('audience', $news?->audience) === 'corporate')>Corporate</option>
            </select>
            @error('audience')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Publicar em
            </label>
            <input type="datetime-local" name="published_at"
                   value="{{ old('published_at', $news?->published_at?->format('Y-m-d\TH:i')) }}"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('published_at') border-red-400 @enderror">
            <p class="mt-1.5 text-xs text-slate-400">Vazio e publicada = agora. Data futura agenda a visibilidade.</p>
            @error('published_at')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Expira em
            </label>
            <input type="datetime-local" name="expires_at"
                   value="{{ old('expires_at', $news?->expires_at?->format('Y-m-d\TH:i')) }}"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('expires_at') border-red-400 @enderror">
            @error('expires_at')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-2 flex items-center gap-3 pt-1">
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="hidden" name="pinned" value="0">
                <input type="checkbox" name="pinned" value="1"
                       {{ old('pinned', $news?->pinned ?? false) ? 'checked' : '' }}
                       class="sr-only peer">
                <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                <span class="ml-2 text-sm font-medium text-slate-700">Fixar no topo</span>
            </label>
        </div>
    </div>
</div>
