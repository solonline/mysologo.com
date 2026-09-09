@extends('docs.layout')

@push('head')
    <style>.scalar-container { height: calc(100vh - 64px); }</style>
@endpush

@section('content')
    <div class="scalar-container" id="scalar-api-reference"></div>
@endsection

@push('scripts')
    <script src="{{ config('docs.scalar_cdn') }}"></script>
    <script>
        Scalar.createApiReference('#scalar-api-reference', Object.assign(
            {
                url: @json($specUrl),
                servers: [{ url: @json(url('/api')), description: 'Ambiente actual' }],
            },
            @json($scalarConfiguration)
        ));
    </script>
@endpush
