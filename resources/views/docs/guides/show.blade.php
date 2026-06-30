@extends('docs.layout')

@section('content')
    <div class="guides-layout">
        <aside class="guides-sidebar">
            <h2>{{ $audience === 'partners' ? 'Parceiros' : 'Equipa interna' }}</h2>
            @foreach ($guides as $item)
                @php
                    $url = $audience === 'partners'
                        ? route('docs.guides.partners.show', $item['slug'])
                        : route('docs.guides.internal.show', $item['slug']);
                @endphp
                <a href="{{ $url }}" @class(['is-active' => $item['slug'] === $slug])>{{ $item['title'] }}</a>
            @endforeach
        </aside>
        <article class="guides-main guide-content">
            {!! $content !!}
        </article>
    </div>
@endsection

@push('scripts')
    <script type="module">
        import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs';
        mermaid.initialize({ theme: 'dark', startOnLoad: false, securityLevel: 'strict' });
        await mermaid.run({ querySelector: '.guide-content .mermaid' });
    </script>
@endpush
