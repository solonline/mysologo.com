{{-- Mobile overlay --}}
<div x-show="sidebarOpen"
     x-cloak
     @click="sidebarOpen = false"
     class="fixed inset-0 bg-black/50 z-20 lg:hidden"
     x-transition:enter="transition-opacity duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity duration-300"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
</div>

<aside
    :class="sidebarCollapsed ? 'w-16' : 'w-64'"
    class="fixed inset-y-0 left-0 z-30 flex flex-col transition-all duration-300 ease-in-out
           bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900
           shadow-2xl
           -translate-x-full lg:translate-x-0
           sidebar-scroll overflow-y-auto"
    :style="sidebarOpen ? 'transform: translateX(0)' : ''"
>

    {{-- Logo --}}
    <div class="flex items-center h-16 px-4 border-b border-white/10 flex-shrink-0">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center flex-shrink-0 shadow-lg shadow-indigo-500/40">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <span x-show="!sidebarCollapsed" x-cloak class="text-white font-bold text-xl tracking-tight">Yoop</span>
        </div>
        <button @click="sidebarCollapsed = !sidebarCollapsed"
                x-show="!sidebarOpen"
                class="ml-auto hidden lg:flex items-center justify-center w-6 h-6 text-slate-400 hover:text-white transition-colors">
            <svg x-show="!sidebarCollapsed" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
            </svg>
            <svg x-show="sidebarCollapsed" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
            </svg>
        </button>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 px-2 py-4 space-y-1">

        {{-- Section: Principal --}}
        <div x-show="!sidebarCollapsed" x-cloak class="px-3 mb-2">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-widest">Principal</span>
        </div>

        <a href="{{ route('dashboard') }}"
           class="sidebar-item {{ request()->routeIs('dashboard') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:text-white transition-all duration-150 cursor-pointer group">
            <svg class="sidebar-icon w-5 h-5 flex-shrink-0 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="text-sm font-medium">Início</span>
        </a>

        {{-- Section: Gestão --}}
        <div x-show="!sidebarCollapsed" x-cloak class="px-3 mb-2 mt-6">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-widest">Gestão</span>
        </div>

        @can('customers.view')
        <a href="{{ route('dashboard.customers') }}"
           class="sidebar-item {{ request()->routeIs('dashboard.customers*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:text-white transition-all duration-150 cursor-pointer group">
            <svg class="sidebar-icon w-5 h-5 flex-shrink-0 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="text-sm font-medium">Clientes</span>
        </a>
        @endcan

        @can('users.view')
        <a href="{{ route('dashboard.users') }}"
           class="sidebar-item {{ request()->routeIs('dashboard.users*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:text-white transition-all duration-150 cursor-pointer group">
            <svg class="sidebar-icon w-5 h-5 flex-shrink-0 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="text-sm font-medium">Utilizadores</span>
        </a>
        @endcan

        @can('roles.view')
        <a href="{{ route('dashboard.roles') }}"
           class="sidebar-item {{ request()->routeIs('dashboard.roles*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:text-white transition-all duration-150 cursor-pointer group">
            <svg class="sidebar-icon w-5 h-5 flex-shrink-0 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="text-sm font-medium">Roles & Permissões</span>
        </a>
        @endcan

        {{-- Section: Financeiro --}}
        <div x-show="!sidebarCollapsed" x-cloak class="px-3 mb-2 mt-6">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-widest">Financeiro</span>
        </div>

         <a href="#"
           class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:text-white transition-all duration-150 cursor-pointer group">
            <svg class="sidebar-icon w-5 h-5 flex-shrink-0 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="text-sm font-medium">Transações</span>
        </a>

        <a href="#"
           class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:text-white transition-all duration-150 cursor-pointer group">
            <svg class="sidebar-icon w-5 h-5 flex-shrink-0 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="text-sm font-medium">Relatórios</span>
        </a>

        @can('stripe_fees.view')
        <a href="{{ route('dashboard.stripe-fees') }}"
           class="sidebar-item {{ request()->routeIs('dashboard.stripe-fees*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:text-white transition-all duration-150 cursor-pointer group">
            <svg class="sidebar-icon w-5 h-5 flex-shrink-0 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="text-sm font-medium">Taxas Stripe</span>
        </a>
        @endcan

        {{-- Section: Sistema --}}
        <div x-show="!sidebarCollapsed" x-cloak class="px-3 mb-2 mt-6">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-widest">Sistema</span>
        </div>

        <a href="{{ route('dashboard.settings') }}"
           class="sidebar-item {{ request()->routeIs('dashboard.settings*') ? 'active' : '' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:text-white transition-all duration-150 cursor-pointer group">
            <svg class="sidebar-icon w-5 h-5 flex-shrink-0 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="text-sm font-medium">Configurações</span>
        </a>

        {{-- Comunicação --}}
        <div x-show="!sidebarCollapsed" x-cloak class="px-3 mb-2 mt-6">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-widest">Comunicação</span>
        </div>

    </nav>

    {{-- User avatar at bottom --}}
    <div class="border-t border-white/10 p-3 flex-shrink-0">
        <div class="flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-white/5 transition-colors cursor-pointer">
            <div class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                {{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 2)) }}
            </div>
            <div x-show="!sidebarCollapsed" x-cloak class="min-w-0">
                <p class="text-sm font-medium text-white truncate">{{ auth()->user()?->name ?? 'Admin' }}</p>
                <p class="text-xs text-slate-400 truncate">{{ auth()->user()?->email ?? '' }}</p>
            </div>
        </div>
    </div>

</aside>
