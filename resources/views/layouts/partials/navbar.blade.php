<header
    class="sticky top-0 z-10 flex items-center h-16 px-4 sm:px-6
                bg-white/80 backdrop-blur-md border-b border-slate-200/60 shadow-sm">

    {{-- Mobile menu toggle --}}
    <button @click="sidebarOpen = true"
        class="lg:hidden mr-3 p-2 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm">
        <span class="text-slate-400 font-medium">
            <a href="{{ route('dashboard') }}" class="hover:text-slate-600 transition-colors">
                <svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Yoop
            </a>
        </span>
        @hasSection('breadcrumb')
        <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-slate-800 font-semibold">@yield('breadcrumb')</span>
        @endif
    </div>

    {{-- Spacer --}}
    <div class="flex-1"></div>

    {{-- Actions --}}
    <div class="flex items-center gap-2">

        {{-- Search --}}
        <div
            class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-slate-100 rounded-lg border border-slate-200 text-slate-400 text-sm hover:bg-slate-50 transition-colors cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <span>Buscar...</span>
            <kbd class="ml-2 text-xs bg-white border border-slate-200 rounded px-1">⌘K</kbd>
        </div>

        {{-- Notifications --}}
        <div class="relative"
            x-data="{
                notifOpen: false,
                notifications: [],
                unreadCount: 0,
                loading: false,

                async fetchNotifications() {
                    this.loading = true;
                    try {
                        const res = await fetch('{{ route('dashboard.notifications') }}', {
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                        });
                        const json = await res.json();
                        this.notifications = json.notifications;
                        this.unreadCount   = json.unread_count;
                    } finally {
                        this.loading = false;
                    }
                },

                async markRead(id) {
                    await fetch('{{ url('dashboard/notifications') }}/' + id + '/read', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                        }
                    });
                    const n = this.notifications.find(n => n.id === id);
                    if (n) { n.read_at = new Date().toISOString(); this.unreadCount = Math.max(0, this.unreadCount - 1); }
                },

                async markAllRead() {
                    await fetch('{{ route('dashboard.notifications.read-all') }}', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                        }
                    });
                    this.notifications.forEach(n => n.read_at = new Date().toISOString());
                    this.unreadCount = 0;
                },

                open() {
                    this.notifOpen = true;
                    this.fetchNotifications();
                },

                init() {
                    this.fetchNotifications();

                    @auth
                    if (window.Echo) {
                        window.Echo.private('dashboard.{{ auth()->id() }}')
                            .listen('.notification.created', (e) => {
                                this.notifications.unshift({
                                    id: e.id,
                                    type: e.type,
                                    title: e.title,
                                    body: e.body,
                                    url: e.url,
                                    read_at: null,
                                    created_at: 'agora'
                                });
                                this.unreadCount++;
                            });
                    }
                    @endauth
                }
            }"
            x-init="init()"
            @click.outside="notifOpen = false">

            <button @click="notifOpen ? notifOpen = false : open()"
                class="relative p-2 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span x-show="unreadCount > 0" x-cloak
                    class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white"
                    x-text="unreadCount > 99 ? '99+' : unreadCount"></span>
            </button>

            {{-- Dropdown --}}
            <div x-show="notifOpen" x-cloak
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="transform opacity-0 scale-95"
                x-transition:enter-end="transform opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="transform opacity-100 scale-100"
                x-transition:leave-end="transform opacity-0 scale-95"
                class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-slate-200/80 z-50 overflow-hidden">

                {{-- Header --}}
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-slate-900">Notificações</span>
                        <span x-show="unreadCount > 0" x-cloak
                            class="text-xs bg-red-100 text-red-600 font-semibold px-1.5 py-0.5 rounded-full"
                            x-text="unreadCount"></span>
                    </div>
                    <button x-show="unreadCount > 0" x-cloak
                        @click="markAllRead()"
                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium transition-colors">
                        Marcar todas lidas
                    </button>
                </div>

                {{-- List --}}
                <div class="max-h-80 overflow-y-auto divide-y divide-slate-50">

                    {{-- Loading --}}
                    <template x-if="loading">
                        <div class="flex items-center justify-center py-8">
                            <svg class="animate-spin w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>
                    </template>

                    {{-- Empty --}}
                    <template x-if="!loading && notifications.length === 0">
                        <div class="flex flex-col items-center justify-center py-10 text-slate-400">
                            <svg class="w-8 h-8 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span class="text-sm">Sem notificações</span>
                        </div>
                    </template>

                    {{-- Items --}}
                    <template x-if="!loading && notifications.length > 0">
                        <div>
                            <template x-for="n in notifications" :key="n.id">
                                <div @click="n.url ? (markRead(n.id), window.location.href = n.url) : markRead(n.id)"
                                    class="flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-slate-50 transition-colors"
                                    :class="n.read_at ? 'opacity-60' : ''">

                                    {{-- Dot unread --}}
                                    <span class="mt-1.5 flex-shrink-0 w-2 h-2 rounded-full"
                                        :class="n.read_at ? 'bg-transparent' : 'bg-indigo-500'"></span>

                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-slate-900 truncate" x-text="n.title"></p>
                                        <p x-show="n.body" class="text-xs text-slate-500 mt-0.5 line-clamp-2" x-text="n.body"></p>
                                        <p class="text-xs text-slate-400 mt-1" x-text="n.created_at"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                </div>

            </div>
        </div>

        {{-- User menu --}}
        <div class="relative" x-data>
            <button @click="userMenu = !userMenu"
                class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
                <div
                    class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white text-xs font-bold shadow">
                    {{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 2)) }}
                </div>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="userMenu" x-cloak @click.outside="userMenu = false"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="transform opacity-0 scale-95"
                x-transition:enter-end="transform opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="transform opacity-100 scale-100"
                x-transition:leave-end="transform opacity-0 scale-95"
                class="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-lg border border-slate-200/80 py-1 z-50">
                <div class="px-4 py-2.5 border-b border-slate-100">
                    <p class="text-sm font-semibold text-slate-900">{{ auth()->user()?->name ?? 'Admin' }}</p>
                    <p class="text-xs text-slate-500">{{ auth()->user()?->email ?? '' }}</p>
                </div>
                <a href="{{ route('dashboard.users.edit', ['user' => auth()->user()?->id]) }}"
                    class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Meu Perfil
                </a>
                <a href="{{ route('dashboard.settings') }}"
                    class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Configurações
                </a>
                <div class="border-t border-slate-100 mt-1 pt-1">
                    <form method="POST" action="{{ route('logout') ?? '#' }}">
                        @csrf
                        <button type="submit"
                            class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Sair
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</header>
