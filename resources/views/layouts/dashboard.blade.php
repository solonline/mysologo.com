<!DOCTYPE html>
<html lang="pt-br" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Yoop') — Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-item:hover .sidebar-icon { transform: scale(1.1); }
        .sidebar-item.active { background: rgba(255,255,255,0.1); border-left: 3px solid #6366f1; }
        .sidebar-item:not(.active):hover { background: rgba(255,255,255,0.05); }
        [x-cloak] { display: none !important; }
        .glass-card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.6);
        }
        .stat-card-gradient-1 { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
        .stat-card-gradient-2 { background: linear-gradient(135deg, #0ea5e9, #38bdf8); }
        .stat-card-gradient-3 { background: linear-gradient(135deg, #10b981, #34d399); }
        .stat-card-gradient-4 { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 2px; }
    </style>
</head>
<body class="h-full bg-slate-100" x-data="{ sidebarOpen: false, sidebarCollapsed: false, userMenu: false, notifOpen: false }">

<div class="flex h-full min-h-screen">

    {{-- ==================== SIDEBAR ==================== --}}
    @include('layouts.partials.sidebar')

    {{-- ==================== MAIN WRAPPER ==================== --}}
    <div class="flex-1 flex flex-col min-w-0 transition-all duration-300"
         :class="sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-64'">

        {{-- ==================== TOP HEADER ==================== --}}
        @include('layouts.partials.navbar')

        {{-- ==================== PAGE CONTENT ==================== --}}
        <main class="flex-1 p-6 overflow-auto">

            {{-- Page header --}}
            @hasSection('page-header')
            <div class="mb-6">
                @yield('page-header')
            </div>
            @endif

            {{-- Flash messages --}}
            @if (session('success'))
            <div class="mb-6 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm">
                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ session('success') }}
            </div>
            @endif

            @if (session('error'))
            <div class="mb-6 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ session('error') }}
            </div>
            @endif

            {{-- Main slot --}}
            @yield('content')

        </main>

        {{-- Footer --}}
        <footer class="px-6 py-4 border-t border-slate-200/60 bg-white/60">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>© {{ date('Y') }} Yoop. Todos os direitos reservados.</span>
                <span>v1.0.0</span>
            </div>
        </footer>

    </div>
</div>

{{-- Alpine.js --}}
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

@stack('scripts')

</body>
</html>
