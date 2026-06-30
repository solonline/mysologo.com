@extends('docs.layout')

@section('content')
    <div class="guides-grid">
        @foreach ($guides as $guide)
            @php
                $url = $audience === 'partners'
                    ? route('docs.guides.partners.show', $guide['slug'])
                    : route('docs.guides.internal.show', $guide['slug']);
            @endphp
            <a href="{{ $url }}" class="guide-card">
                <h3>{{ $guide['title'] }}</h3>
                <p>{{ $guide['description'] }}</p>
            </a>
        @endforeach
    </div>
@endsection
