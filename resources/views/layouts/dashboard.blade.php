<!DOCTYPE html>
<html lang="pt-br" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Yoop') — Dashboard</title>
    <link rel="icon" href="{{ asset('yoop.jpeg') }}" type="image/jpeg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300..900;1,14..32,300..900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --brand:      #7c3aed;
            --brand-d:    #6d28d9;
            --brand-l:    #a78bfa;
            --brand-glow: rgba(124,58,237,.18);
            --sidebar-bg: #0d0d1a;
        }

        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; }

        /* ── Sidebar ──────────────────────────────── */
        .sidebar-item {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 500;
            color: #94a3b8;
            text-decoration: none;
            transition: background .15s, color .15s, transform .1s;
            cursor: pointer;
        }
        .sidebar-item:hover { background: rgba(255,255,255,.06); color: #e2e8f0; }
        .sidebar-item.active {
            background: linear-gradient(135deg, rgba(124,58,237,.25), rgba(109,40,217,.15));
            color: #c4b5fd;
            border: 1px solid rgba(124,58,237,.25);
        }
        .sidebar-item.active .sidebar-icon { color: var(--brand-l); filter: drop-shadow(0 0 4px rgba(167,139,250,.4)); }
        .sidebar-item:not(.active):hover .sidebar-icon { transform: scale(1.12); }
        .sidebar-icon { transition: transform .15s, color .15s, filter .15s; flex-shrink: 0; }

        /* ── Cards ──────────────────────────────── */
        .card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,.05);
            box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 6px 20px rgba(0,0,0,.04);
            transition: box-shadow .2s;
        }
        .card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.06), 0 12px 32px rgba(0,0,0,.06); }

        /* ── Stat cards ─────────────────────────── */
        .stat-icon-1 { background: linear-gradient(135deg,#7c3aed,#a855f7); box-shadow: 0 6px 16px rgba(124,58,237,.35); }
        .stat-icon-2 { background: linear-gradient(135deg,#0ea5e9,#38bdf8); box-shadow: 0 6px 16px rgba(14,165,233,.35); }
        .stat-icon-3 { background: linear-gradient(135deg,#10b981,#34d399); box-shadow: 0 6px 16px rgba(16,185,129,.35); }
        .stat-icon-4 { background: linear-gradient(135deg,#f59e0b,#fbbf24); box-shadow: 0 6px 16px rgba(245,158,11,.35); }

        /* ── Badges ─────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .badge-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }

        /* ── Tables ─────────────────────────────── */
        .data-table thead th {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #64748b;
            background: #f8fafc;
            padding: 10px 16px;
        }
        .data-table tbody tr { border-bottom: 1px solid rgba(0,0,0,.04); transition: background .12s; }
        .data-table tbody tr:last-child { border-bottom: none; }
        .data-table tbody tr:hover { background: #fafafa; }
        .data-table tbody td { padding: 12px 16px; font-size: 13.5px; color: #334155; }

        /* ── Buttons ─────────────────────────────── */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: linear-gradient(135deg, var(--brand), var(--brand-d));
            color: #fff;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(124,58,237,.35), inset 0 1px 0 rgba(255,255,255,.15);
            transition: transform .15s cubic-bezier(.34,1.56,.64,1), box-shadow .15s;
            text-decoration: none;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(124,58,237,.4); }
        .btn-primary:active { transform: scale(.97); }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #fff;
            color: #374151;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            border: 1.5px solid #e2e8f0;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
            transition: background .12s, border-color .12s, box-shadow .12s;
            text-decoration: none;
        }
        .btn-secondary:hover { background: #f8fafc; border-color: #cbd5e1; box-shadow: 0 2px 6px rgba(0,0,0,.06); }

        .btn-danger {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            background: #fff;
            color: #dc2626;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            border: 1.5px solid rgba(220,38,38,.25);
            cursor: pointer;
            transition: background .12s, border-color .12s;
            text-decoration: none;
        }
        .btn-danger:hover { background: #fef2f2; border-color: rgba(220,38,38,.4); }

        .btn-sm { padding: 5px 12px; font-size: 12px; border-radius: 8px; }

        /* ── Forms ──────────────────────────────── */
        .form-input, .form-select, .form-textarea {
            width: 100%;
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 9px 14px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            transition: border-color .15s, box-shadow .15s;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand-glow), 0 1px 2px rgba(0,0,0,.04);
        }
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 5px;
            letter-spacing: .03em;
        }

        /* ── Page header ────────────────────────── */
        .page-title { font-size: 22px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; }
        .page-subtitle { font-size: 13px; color: #64748b; margin-top: 2px; }

        /* ── Section header ─────────────────────── */
        .section-title { font-size: 14px; font-weight: 700; color: #0f172a; }
        .section-sub { font-size: 12px; color: #94a3b8; margin-top: 1px; }

        /* ── Scrollbar ──────────────────────────── */
        .sidebar-scroll::-webkit-scrollbar { width: 3px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.12); border-radius: 2px; }

        /* ── Content entrance (sidebar é estática, só o conteúdo anima) ── */
        /* Não deixar transform no estado final: fill-mode `both` + translateY(0)
           cria containing block e os overlays `fixed`/`backdrop-blur` ficam
           presos à coluna do main (sidebar, navbar e footer ficam de fora). */
        @keyframes contentFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; }
        }
        main {
            animation: contentFadeIn .35s cubic-bezier(.4,0,.2,1) both;
        }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full bg-slate-50" x-data="{ sidebarOpen: false, sidebarCollapsed: false, userMenu: false }">

<div class="flex h-full min-h-screen">

    {{-- ==================== SIDEBAR ==================== --}}
    @include('layouts.partials.sidebar')

    {{-- ==================== MAIN WRAPPER ==================== --}}
    <div class="flex-1 flex flex-col min-w-0 transition-all duration-300"
         :class="sidebarCollapsed ? 'lg:ml-[68px]' : 'lg:ml-64'">

        {{-- ==================== TOP HEADER ==================== --}}
        @include('layouts.partials.navbar')

        {{-- ==================== PAGE CONTENT ==================== --}}
        <main class="flex-1 p-6 overflow-auto">

            {{-- Page header --}}
            @hasSection('page-header')
            <div class="mb-7">
                @yield('page-header')
            </div>
            @endif

            {{-- Flash: success --}}
            @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="mb-6 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm shadow-sm">
                <div class="flex-shrink-0 w-7 h-7 rounded-full bg-emerald-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <span class="flex-1 font-medium">{{ session('success') }}</span>
                <button @click="show = false" class="text-emerald-400 hover:text-emerald-600 transition-colors flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            @endif

            {{-- Flash: error --}}
            @if (session('error'))
            <div x-data="{ show: true }" x-show="show" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="mb-6 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm shadow-sm">
                <div class="flex-shrink-0 w-7 h-7 rounded-full bg-red-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="flex-1 font-medium">{{ session('error') }}</span>
                <button @click="show = false" class="text-red-400 hover:text-red-600 transition-colors flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            @endif

            {{-- Main slot --}}
            @yield('content')

        </main>

        {{-- Footer --}}
        <footer class="px-6 py-3 border-t border-slate-200/50">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>© {{ date('Y') }} Yoop · Todos os direitos reservados.</span>
                <span class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    v1.0.0
                </span>
            </div>
        </footer>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

@stack('scripts')

</body>
</html>
