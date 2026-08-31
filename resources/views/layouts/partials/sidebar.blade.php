{{-- Mobile overlay --}}
<div x-show="sidebarOpen"
     x-cloak
     @click="sidebarOpen = false"
     class="fixed inset-0 bg-black/60 z-20 lg:hidden backdrop-blur-sm"
     x-transition:enter="transition-opacity duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
</div>

<aside
    :class="sidebarCollapsed ? 'w-[68px]' : 'w-64'"
    class="fixed inset-y-0 left-0 z-30 flex flex-col transition-all duration-300 ease-in-out
           shadow-2xl -translate-x-full lg:translate-x-0
           sidebar-scroll overflow-y-auto overflow-x-hidden"
    :style="`background: linear-gradient(180deg, #1e1b4b 0%, #2d1b69 45%, #1a1245 100%); border-right: 1px solid rgba(124,58,237,.2); ${sidebarOpen ? 'transform: translateX(0)' : ''}`"
>

    {{-- Logo --}}
    <div class="flex items-center h-[64px] px-4 flex-shrink-0" style="border-bottom: 1px solid rgba(255,255,255,.06);">
        <div class="flex items-center gap-3 min-w-0 flex-1">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: linear-gradient(135deg,#7c3aed,#6d28d9); box-shadow: 0 4px 14px rgba(124,58,237,.45);">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div x-show="!sidebarCollapsed" x-cloak class="min-w-0">
                <span class="text-white font-bold text-lg tracking-tight">Yoop</span>
                <span class="text-[10px] font-medium block" style="color:rgba(167,139,250,.6);margin-top:-2px;letter-spacing:.08em">DASHBOARD</span>
            </div>
        </div>
        <button @click="sidebarCollapsed = !sidebarCollapsed"
                x-show="!sidebarOpen"
                class="hidden lg:flex items-center justify-center w-6 h-6 rounded-md transition-colors flex-shrink-0"
                style="color: rgba(148,163,184,.5);"
                onmouseenter="this.style.color='#e2e8f0'" onmouseleave="this.style.color='rgba(148,163,184,.5)'">
            <svg x-show="!sidebarCollapsed" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
            </svg>
            <svg x-show="sidebarCollapsed" x-cloak class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
            </svg>
        </button>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 px-3 py-4 space-y-0.5">

        {{-- Section: Principal --}}
        <div x-show="!sidebarCollapsed" x-cloak class="px-2 pb-1.5 pt-1">
            <span class="text-[10px] font-700 uppercase tracking-[.12em]" style="color:rgba(148,163,184,.4);font-weight:700">Principal</span>
        </div>
        <div x-show="sidebarCollapsed" x-cloak class="h-2"></div>

        <a href="{{ route('dashboard') }}"
           class="sidebar-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <svg class="sidebar-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak>Início</span>
        </a>

        {{-- Section: Gestão --}}
        <div x-show="!sidebarCollapsed" x-cloak class="px-2 pb-1.5 pt-4">
            <span class="text-[10px] font-700 uppercase tracking-[.12em]" style="color:rgba(148,163,184,.4);font-weight:700">Gestão</span>
        </div>
        <div x-show="sidebarCollapsed" x-cloak class="h-3"></div>

        @can('customers.view')
        <a href="{{ route('dashboard.customers') }}"
           class="sidebar-item {{ request()->routeIs('dashboard.customers*') ? 'active' : '' }}">
            <svg class="sidebar-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak>Clientes</span>
        </a>
        @endcan

        @can('users.view')
        <a href="{{ route('dashboard.users') }}"
           class="sidebar-item {{ request()->routeIs('dashboard.users*') ? 'active' : '' }}">
            <svg class="sidebar-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak>Utilizadores</span>
        </a>
        @endcan

        @can('roles.view')
        <a href="{{ route('dashboard.roles') }}"
           class="sidebar-item {{ request()->routeIs('dashboard.roles*') ? 'active' : '' }}">
            <svg class="sidebar-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak>Roles & Permissões</span>
        </a>
        @endcan

        {{-- Section: Financeiro --}}
        <div x-show="!sidebarCollapsed" x-cloak class="px-2 pb-1.5 pt-4">
            <span class="text-[10px] font-700 uppercase tracking-[.12em]" style="color:rgba(148,163,184,.4);font-weight:700">Financeiro</span>
        </div>
        <div x-show="sidebarCollapsed" x-cloak class="h-3"></div>

        @can('transactions.view')
        <a href="{{ route('dashboard.transactions') }}"
           class="sidebar-item {{ request()->routeIs('dashboard.transactions*') ? 'active' : '' }}">
            <svg class="sidebar-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak>Transações</span>
        </a>
        @endcan

        @can('transactions.view')
        <a href="{{ route('dashboard.platform-revenue') }}"
           class="sidebar-item {{ request()->routeIs('dashboard.platform-revenue*') ? 'active' : '' }}">
            <svg class="sidebar-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak>Receita Platform</span>
        </a>
        @endcan

        <a href="#" class="sidebar-item">
            <svg class="sidebar-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak>Relatórios</span>
        </a>

        @can('accounts_payable.view')
        <a href="{{ route('dashboard.accounts-payable') }}"
           class="sidebar-item {{ request()->routeIs('dashboard.accounts-payable*') ? 'active' : '' }}">
            <svg class="sidebar-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14h6m-6-4h6m-7 10h8a2 2 0 002-2V7.414a2 2 0 00-.586-1.414l-2.414-2.414A2 2 0 0013.586 3H8a2 2 0 00-2 2v13a2 2 0 002 2z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak>Contas a Pagar</span>
        </a>
        @endcan

        @can('stripe_fees.view')
        <a href="{{ route('dashboard.stripe-fees') }}"
           class="sidebar-item {{ request()->routeIs('dashboard.stripe-fees*') ? 'active' : '' }}">
            <svg class="sidebar-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak>Taxas Stripe</span>
        </a>
        @endcan

        {{-- Section: Sistema --}}
        <div x-show="!sidebarCollapsed" x-cloak class="px-2 pb-1.5 pt-4">
            <span class="text-[10px] font-700 uppercase tracking-[.12em]" style="color:rgba(148,163,184,.4);font-weight:700">Sistema</span>
        </div>
        <div x-show="sidebarCollapsed" x-cloak class="h-3"></div>

        @can('audit_logs.view')
        <a href="{{ route('dashboard.audit-logs') }}"
           class="sidebar-item {{ request()->routeIs('dashboard.audit-logs*') ? 'active' : '' }}">
            <svg class="sidebar-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak>Auditoria</span>
        </a>
        @endcan

        <a href="{{ route('dashboard.settings') }}"
           class="sidebar-item {{ request()->routeIs('dashboard.settings*') ? 'active' : '' }}">
            <svg class="sidebar-icon w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak>Configurações</span>
        </a>

    </nav>

    {{-- User profile at bottom --}}
    <div class="flex-shrink-0 p-3" style="border-top: 1px solid rgba(255,255,255,.06);">
        <div class="flex items-center gap-3 p-2 rounded-xl transition-colors cursor-pointer"
             style="hover:background:rgba(255,255,255,.05);"
             onmouseenter="this.style.background='rgba(255,255,255,.05)'"
             onmouseleave="this.style.background='transparent'">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                 style="background: linear-gradient(135deg,#7c3aed,#a855f7); box-shadow: 0 2px 8px rgba(124,58,237,.4);">
                {{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 2)) }}
            </div>
            <div x-show="!sidebarCollapsed" x-cloak class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-white truncate leading-none mb-0.5">{{ auth()->user()?->name ?? 'Admin' }}</p>
                <p class="text-xs truncate" style="color:rgba(148,163,184,.6)">{{ auth()->user()?->email ?? '' }}</p>
            </div>
            <div x-show="!sidebarCollapsed" x-cloak class="flex-shrink-0">
                <div class="w-1.5 h-1.5 rounded-full" style="background:#34d399;box-shadow:0 0 4px rgba(52,211,153,.6)"></div>
            </div>
        </div>
    </div>

</aside>
