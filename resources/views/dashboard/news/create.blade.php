@extends('layouts.dashboard')

@section('title', 'Nova notícia')
@section('breadcrumb', 'Notícias')

@section('page-header')
<div class="flex items-center gap-3">
    <a href="{{ route('dashboard.news') }}"
       class="p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <h1 class="text-2xl font-bold text-slate-900">Nova notícia</h1>
</div>
@endsection

@section('content')
<div class="max-w-2xl">
    <form id="news-form" method="POST" action="{{ route('dashboard.news.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf

        @include('dashboard.news._form')

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm shadow-indigo-500/25">
                Criar notícia
            </button>
            <a href="{{ route('dashboard.news') }}"
               class="px-5 py-2.5 text-sm text-red-600 bg-red-50 rounded-xl hover:bg-red-100 transition">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.4/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#news-body',
    menubar: false,
    branding: false,
    height: 320,
    plugins: 'lists link',
    toolbar: 'bold italic underline | bullist numlist | h2 h3 | link | undo redo',
    content_style: 'body { font-family: Inter, sans-serif; font-size: 14px; }',
    valid_elements: 'p,br,strong/b,em/i,u,ul,ol,li,a[href],h2,h3,blockquote',
    convert_urls: false
});
document.getElementById('news-form')?.addEventListener('submit', () => {
    if (window.tinymce) tinymce.triggerSave();
});
</script>
@endpush
