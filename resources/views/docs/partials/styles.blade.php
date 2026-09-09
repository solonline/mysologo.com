<style>
    :root {
        --yoop-bg: #0f1419;
        --yoop-surface: #1a2332;
        --yoop-surface-2: #232f42;
        --yoop-border: #2d3a4f;
        --yoop-text: #e8edf4;
        --yoop-muted: #8b9cb3;
        --yoop-accent: #6366f1;
        --yoop-accent-soft: rgba(99, 102, 241, 0.12);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'DM Sans', system-ui, sans-serif;
        background: var(--yoop-bg);
        color: var(--yoop-text);
        min-height: 100vh;
        line-height: 1.6;
    }

    .docs-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.875rem 1.5rem;
        background: var(--yoop-surface);
        border-bottom: 1px solid var(--yoop-border);
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .docs-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
    }

    .docs-brand-logo {
        height: 36px;
        width: auto;
        display: block;
        flex-shrink: 0;
        mix-blend-mode: screen;
    }

    .docs-brand-text {
        min-width: 0;
    }

    .docs-header-right {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-shrink: 0;
    }

    .docs-securepay {
        height: 28px;
        width: auto;
        display: block;
        flex-shrink: 0;
        mix-blend-mode: screen;
    }

    .docs-title {
        font-size: 1rem;
        font-weight: 600;
        letter-spacing: -0.02em;
    }

    .docs-subtitle {
        font-size: 0.75rem;
        color: var(--yoop-muted);
        margin-top: 0.125rem;
    }

    .docs-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 0.375rem;
        justify-content: flex-end;
    }

    .docs-nav a {
        color: var(--yoop-muted);
        text-decoration: none;
        font-size: 0.8125rem;
        font-weight: 500;
        padding: 0.45rem 0.75rem;
        border-radius: 6px;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .docs-nav a:hover {
        color: var(--yoop-text);
        background: var(--yoop-accent-soft);
        border-color: var(--yoop-border);
    }

    .docs-nav a.is-active {
        color: var(--yoop-text);
        background: var(--yoop-accent-soft);
        border-color: var(--yoop-accent);
    }

    .docs-nav .nav-sep {
        width: 1px;
        background: var(--yoop-border);
        margin: 0.25rem 0.25rem;
        align-self: stretch;
    }

    .scalar-container {
        height: calc(100vh - 64px);
    }

    .guides-layout {
        display: grid;
        grid-template-columns: 260px 1fr;
        min-height: calc(100vh - 64px);
    }

    .guides-sidebar {
        border-right: 1px solid var(--yoop-border);
        background: var(--yoop-surface);
        padding: 1.25rem 1rem;
        position: sticky;
        top: 64px;
        height: calc(100vh - 64px);
        overflow-y: auto;
    }

    .guides-sidebar h2 {
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--yoop-muted);
        margin-bottom: 0.75rem;
    }

    .guides-sidebar a {
        display: block;
        color: var(--yoop-muted);
        text-decoration: none;
        font-size: 0.875rem;
        padding: 0.5rem 0.625rem;
        border-radius: 6px;
        margin-bottom: 0.125rem;
    }

    .guides-sidebar a:hover,
    .guides-sidebar a.is-active {
        color: var(--yoop-text);
        background: var(--yoop-accent-soft);
    }

    .guides-main {
        padding: 2rem 2.5rem 4rem;
        max-width: 52rem;
    }

    .guides-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
        padding: 2rem;
        max-width: 1100px;
        margin: 0 auto;
    }

    .guide-card {
        background: var(--yoop-surface);
        border: 1px solid var(--yoop-border);
        border-radius: 12px;
        padding: 1.25rem;
        text-decoration: none;
        color: inherit;
        transition: border-color 0.15s, transform 0.15s;
    }

    .guide-card:hover {
        border-color: var(--yoop-accent);
        transform: translateY(-2px);
    }

    .guide-card h3 {
        font-size: 1rem;
        margin-bottom: 0.5rem;
    }

    .guide-card p {
        font-size: 0.875rem;
        color: var(--yoop-muted);
    }

    .guide-content h1 {
        font-size: 1.75rem;
        letter-spacing: -0.03em;
        margin-bottom: 0.75rem;
        line-height: 1.25;
    }

    .guide-content h2 {
        font-size: 1.25rem;
        margin: 2rem 0 0.75rem;
        padding-top: 0.5rem;
        border-top: 1px solid var(--yoop-border);
    }

    .guide-content h3 {
        font-size: 1.05rem;
        margin: 1.5rem 0 0.5rem;
    }

    .guide-content p,
    .guide-content li {
        color: #c5d0de;
        margin-bottom: 0.75rem;
    }

    .guide-content ul,
    .guide-content ol {
        padding-left: 1.25rem;
        margin-bottom: 1rem;
    }

    .guide-content a {
        color: #a5b4fc;
    }

    .guide-content code {
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.85em;
        background: var(--yoop-surface-2);
        padding: 0.15rem 0.4rem;
        border-radius: 4px;
    }

    .guide-content pre {
        background: var(--yoop-surface);
        border: 1px solid var(--yoop-border);
        border-radius: 8px;
        padding: 1rem;
        overflow-x: auto;
        margin: 1rem 0;
    }

    .guide-content pre code {
        background: none;
        padding: 0;
    }

    .guide-content table {
        width: 100%;
        border-collapse: collapse;
        margin: 1rem 0;
        font-size: 0.875rem;
    }

    .guide-content th,
    .guide-content td {
        border: 1px solid var(--yoop-border);
        padding: 0.5rem 0.75rem;
        text-align: left;
    }

    .guide-content th {
        background: var(--yoop-surface);
    }

    .guide-content blockquote {
        border-left: 3px solid var(--yoop-accent);
        padding-left: 1rem;
        color: var(--yoop-muted);
        margin: 1rem 0;
    }

    .guide-content .mermaid {
        background: var(--yoop-surface);
        border: 1px solid var(--yoop-border);
        border-radius: 8px;
        padding: 1rem;
        margin: 1.25rem 0;
    }

    @media (max-width: 900px) {
        .guides-layout { grid-template-columns: 1fr; }
        .guides-sidebar {
            position: static;
            height: auto;
            border-right: none;
            border-bottom: 1px solid var(--yoop-border);
        }
        .guides-main { padding: 1.5rem; }
        .docs-header { flex-wrap: wrap; }
        .docs-subtitle { display: none; }
        .docs-header-right { width: 100%; justify-content: space-between; }
        .docs-securepay { height: 24px; }
    }
</style>
