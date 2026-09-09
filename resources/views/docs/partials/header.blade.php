<header class="docs-header">
    <div class="docs-brand">
        <img src="{{ asset('LOGO YOOP 2.png') }}" alt="Yoop" class="docs-brand-logo">
        <div class="docs-brand-text">
            <div class="docs-title">{{ $title }}</div>
            @isset($description)
                <div class="docs-subtitle">{{ $description }}</div>
            @endisset
        </div>
    </div>
    <div class="docs-header-right">
        <nav class="docs-nav">
            <a href="{{ route('docs.guides.internal') }}" @class(['is-active' => ($navActive ?? '') === 'guides-internal'])>Guias</a>
            <a href="{{ route('docs.internal') }}" @class(['is-active' => ($navActive ?? '') === 'api-internal'])>API</a>
            <span class="nav-sep" aria-hidden="true"></span>
            <a href="{{ route('docs.guides.partners') }}" @class(['is-active' => ($navActive ?? '') === 'guides-partners'])>Guias parceiros</a>
            <a href="{{ route('docs.partners') }}" @class(['is-active' => ($navActive ?? '') === 'api-partners'])>API parceiros</a>
        </nav>
        <img src="{{ asset('securepay.png') }}" alt="SecurePay" class="docs-securepay">
    </div>
</header>
