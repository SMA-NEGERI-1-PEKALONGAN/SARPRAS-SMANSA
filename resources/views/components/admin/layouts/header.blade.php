<header
    class="sticky top-0 z-[50] w-full border-b border-gray-100 bg-white transition-all duration-300 ease-in-out dark:border-gray-900 dark:bg-gray-950"
    x-data="{ 
        searchOpen: false, 
        searchQuery: '',
        searchItems: @js($searchItems),
        get filteredResults() {
            if (this.searchQuery === '') return [];
            return this.searchItems.filter(item => 
                item.title.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                item.category.toLowerCase().includes(this.searchQuery.toLowerCase())
            );
        }
    }" 
    @keydown.window.escape="searchOpen = false"
    @keydown.window.slash.prevent="searchOpen = true"
    x-cloak>

    <div class="flex items-center justify-between px-4 py-3 sm:px-6">

        {{-- LEFT AREA: Toggler & Search Trigger --}}
        <div class="flex items-center gap-4">
            {{-- Desktop Toggler --}}
            <button @click="$store.sidebar.toggleExpanded()"
                class="items-center justify-center hidden w-10 h-10 text-gray-500 transition-all bg-white border border-gray-100 rounded-xl hover:bg-gray-50 hover:text-indigo-600 active:scale-95 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 xl:flex">
                <i class="text-xl transition-transform duration-500 ti"
                    :class="$store.sidebar.isExpanded ? 'ti-layout-sidebar-left-collapse' : 'ti-layout-sidebar-right-collapse rotate-180'">
                </i>
            </button>

            {{-- Mobile Toggler --}}
            <button @click="$store.sidebar.toggleMobileOpen()"
                class="flex items-center justify-center w-10 h-10 text-gray-500 transition-all bg-white border border-gray-100 rounded-xl hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 xl:hidden">
                <i class="text-xl ti ti-menu-2"></i>
            </button>

            {{-- SEARCH TRIGGER BUTTON --}}
            <button @click="searchOpen = true" 
                class="items-center hidden w-64 h-10 gap-3 px-4 text-gray-400 transition-all border border-transparent md:flex rounded-xl bg-gray-50 dark:bg-gray-900 hover:border-indigo-500/20 group">
                <i class="text-lg transition-colors ti ti-search group-hover:text-indigo-600"></i>
                <span class="text-sm font-medium">Cari sesuatu...</span>
                <span class="ml-auto text-[10px] font-bold bg-white dark:bg-gray-800 px-1.5 py-0.5 rounded border dark:border-gray-700 shadow-sm">/</span>
            </button>
        </div>

        {{-- RIGHT AREA: Actions & Profile --}}
        <div class="flex items-center gap-1.5 sm:gap-3">

            {{-- THEME SWITCHER --}}
            <button @click="$store.theme.toggle()"
                class="relative flex items-center justify-center w-10 h-10 text-gray-500 transition-all duration-300 rounded-xl hover:bg-gray-100 active:scale-90 dark:text-gray-400 dark:hover:bg-gray-900"
                title="Ganti Tema">
                <i class="absolute text-xl transition-all duration-500 ti ti-moon" x-show="$store.theme.theme === 'light'" x-transition></i>
                <i class="absolute text-xl transition-all duration-500 ti ti-sun text-amber-400" x-show="$store.theme.theme === 'dark'" x-transition></i>
            </button>

            {{-- NOTIFICATIONS --}}
            {{-- <div class="relative" x-data="{ open: false }">
                <button @click="open = !open"
                    class="relative flex items-center justify-center w-10 h-10 text-gray-500 transition-all rounded-xl hover:bg-gray-100 active:scale-95 dark:text-gray-400 dark:hover:bg-gray-900">
                    <i class="text-xl ti ti-bell"></i>
                    @if ($unreadCount > 0)
                        <span class="absolute right-2.5 top-2.5 flex h-2 w-2">
                            <span class="absolute inline-flex w-full h-full rounded-full opacity-75 animate-ping bg-rose-400"></span>
                            <span class="relative inline-flex w-2 h-2 rounded-full bg-rose-500"></span>
                        </span>
                    @endif
                </button>

                <div x-show="open" @click.outside="open = false" 
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    class="absolute right-0 mt-3 w-[22rem] sm:w-[26rem] origin-top-right rounded-3xl border border-gray-100 bg-white shadow-2xl shadow-indigo-100 dark:border-gray-800 dark:bg-gray-950 dark:shadow-none"
                    x-cloak>

                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-50 dark:border-gray-900">
                        <h3 class="text-sm font-bold text-gray-400">Notifikasi</h3>
                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-[10px] font-bold text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">{{ $unreadCount }} Baru</span>
                    </div>

                    <div class="max-h-[400px] overflow-y-auto py-2 custom-scrollbar">
                        @foreach ($latestNotifications as $notif)
                            <a href="{{ $notif->data['url'] }}" class="flex items-start gap-4 px-6 py-4 transition-all duration-200 border-b hover:bg-indigo-50/50 dark:hover:bg-indigo-500/5 group border-gray-50/50 dark:border-gray-900/50 last:border-none">
                                <div class="flex items-center justify-center text-gray-400 transition-colors h-11 w-11 shrink-0 rounded-2xl bg-gray-50 group-hover:bg-white group-hover:text-indigo-600 dark:bg-gray-900 dark:group-hover:bg-gray-800">
                                    <i class="ti {{ $notif->data['icon'] }} text-xl"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start mb-0.5">
                                        <p class="text-sm font-bold text-gray-900 truncate transition-colors dark:text-white group-hover:text-indigo-600">{{ $notif->data['title'] }}</p>
                                        @if(!$notif->read_at)<span class="h-1.5 w-1.5 rounded-full bg-indigo-500 shadow-[0_0_8px_rgba(79,70,229,0.6)]"></span>@endif
                                    </div>
                                    <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400 line-clamp-2">{{ $notif->data['message'] }}</p>
                                    <p class="mt-2 text-[10px] font-bold text-gray-400 ">{{ $notif->created_at->diffForHumans() }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                    <div class="p-4 border-t border-gray-50 dark:border-gray-900">
                        <x-button variant="secondary" class="w-full py-2.5 text-xs font-bold ">Tandai Semua Terbaca</x-button>
                    </div>
                </div>
            </div> --}}

            <div class="w-px h-6 mx-1 bg-gray-200 dark:bg-gray-800"></div>

            {{-- PROFILE DROPDOWN --}}
            <div class="relative" x-data="{ userOpen: false }">
                <button @click="userOpen = !userOpen" class="flex items-center gap-3 p-1 rounded-2xl group">
                    <div class="flex-col items-end hidden md:flex">
                        <p class="mb-1 text-sm font-bold leading-none text-gray-900 truncate transition-colors dark:text-white group-hover:text-indigo-600">{{ $user->name }}</p>
                        <p class="text-xs font-bold text-gray-400 truncate ">{{ $user->role }}</p>
                    </div>
                    <x-avatar :src="$user->avatar ?? null" :name="$user->name" size="sm" shape="xl" status="online" />
                </button>

                <div x-show="userOpen" @click.outside="userOpen = false"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    class="absolute right-0 z-50 w-64 p-2 mt-3 origin-top-right bg-white border border-gray-100 rounded-3xl dark:border-gray-900 dark:bg-gray-950"
                    x-cloak>

                    <div class="flex items-center gap-3 px-4 py-4 mb-2 border-b border-gray-50 dark:border-gray-900">
                        <x-avatar :src="$user->avatar ?? null" :name="$user->name" size="md" shape="xl" />
                        <div class="flex-1 overflow-hidden">
                            <p class="mb-1 text-sm font-bold leading-none text-gray-900 truncate dark:text-white">{{ $user->name }}</p>
                            <p class="truncate text-[10px] font-medium text-gray-400">{{ $user->email }}</p>
                        </div>
                    </div>

                    <div class="space-y-1">
                        {{-- <a href="#" class="flex items-center gap-3 rounded-2xl px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors hover:bg-indigo-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-400">
                            <i class="text-lg text-indigo-500 ti ti-user-circle"></i> Profil Saya
                        </a>
                        <a href="#" class="flex items-center gap-3 rounded-2xl px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors hover:bg-indigo-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-400">
                            <i class="text-lg text-indigo-500 ti ti-settings"></i> Pengaturan
                        </a>
                        <hr class="mx-4 my-2 border-gray-50 dark:border-gray-900"> --}}
                        {{-- <button class="flex w-full items-center gap-3 rounded-2xl px-4 py-2.5 text-sm font-bold text-rose-600 transition-colors hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                            <i class="text-lg ti ti-logout-2"></i> Keluar Sistem
                        </button> --}}
                        <a href="{{ route('logout') }}" wire:navigate class="flex w-full items-center gap-3 rounded-2xl px-4 py-2.5 text-sm font-bold text-rose-600 transition-colors hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                            <i class="text-lg ti ti-logout-2"></i> Keluar Sistem
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SEARCH MODAL OVERLAY --}}
    <template x-teleport="body">
        <div x-show="searchOpen" class="fixed inset-0 z-[10001] flex items-start justify-center p-4 md:p-12" x-cloak>
            {{-- Backdrop --}}
            <div x-show="searchOpen" x-transition.opacity @click="searchOpen = false" class="fixed inset-0 bg-gray-900/60 backdrop-blur-md"></div>
            
            {{-- Modal Content --}}
            <div x-show="searchOpen" 
                 x-transition:enter="transition duration-300 ease-out" 
                 x-transition:enter-start="opacity-0 -translate-y-8 scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition duration-200 ease-in" 
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100" 
                 x-transition:leave-end="opacity-0 -translate-y-8 scale-95"
                 class="relative w-full max-w-2xl bg-white dark:bg-gray-950 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-2xl overflow-hidden mt-10 flex flex-col">
                
                {{-- Search Input Area --}}
                <div class="flex items-center gap-4 p-6 border-b border-gray-100 dark:border-gray-800">
                    <i class="text-2xl text-indigo-600 ti ti-search"></i>
                    <input type="text" 
                           x-model="searchQuery"
                           class="flex-1 text-lg font-medium placeholder-gray-400 bg-transparent border-none outline-none dark:text-white" 
                           placeholder="Mencari file, produk, atau halaman..." 
                           x-init="$watch('searchOpen', value => { if(value) { setTimeout(() => $el.focus(), 100); searchQuery = ''; } })"
                           @keydown.escape="searchOpen = false">
                    <button @click="searchOpen = false" class="text-[10px] font-bold text-gray-400 px-2 py-1 bg-gray-50 dark:bg-gray-900 rounded-lg border dark:border-gray-800">ESC</button>
                </div>

                {{-- Results Area --}}
                <div class="max-h-[400px] overflow-y-auto custom-scrollbar">
                    <div x-show="searchQuery === ''" class="p-12 text-center">
                        <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 text-indigo-600 bg-indigo-50 dark:bg-indigo-500/10 rounded-2xl">
                            <i class="text-3xl ti ti-command"></i>
                        </div>
                        <p class="mb-1 text-sm font-bold text-gray-900 dark:text-white">Pencarian Cepat</p>
                        <p class="text-xs text-gray-400">Ketik nama halaman atau produk untuk navigasi instan.</p>
                    </div>

                    <div x-show="searchQuery !== '' && filteredResults.length > 0" class="p-4 space-y-1">
                        <template x-for="item in filteredResults" :key="item.title">
                            <a :href="item.url" class="flex items-center gap-4 p-4 transition-all rounded-2xl hover:bg-indigo-50 dark:hover:bg-indigo-500/10 group">
                                <div class="flex items-center justify-center w-10 h-10 text-gray-400 transition-colors rounded-xl bg-gray-50 dark:bg-gray-900 group-hover:text-indigo-600">
                                    <i class="ti" :class="item.icon"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-gray-900 dark:text-white" x-text="item.title"></p>
                                    <p class="text-[10px] font-black uppercase text-gray-400 tracking-widest" x-text="item.category"></p>
                                </div>
                                <i class="ti ti-arrow-right text-indigo-500 opacity-0 group-hover:opacity-100 transition-all translate-x-[-10px] group-hover:translate-x-0"></i>
                            </a>
                        </template>
                    </div>

                    <div x-show="searchQuery !== '' && filteredResults.length === 0" class="p-12 text-center">
                        <i class="block mb-4 text-4xl text-gray-300 ti ti-search-off"></i>
                        <p class="text-sm font-bold text-gray-400">Tidak ada hasil untuk "<span x-text="searchQuery"></span>"</p>
                    </div>
                </div>

                {{-- Footer Info --}}
                <div class="flex items-center justify-center gap-6 p-4 border-t border-gray-100 bg-gray-50/50 dark:bg-gray-900/50 dark:border-gray-800">
                    <div class="flex items-center gap-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                        <span class="px-1.5 py-0.5 rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm text-[8px]">ENTER</span> Pilih
                    </div>
                    <div class="flex items-center gap-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                        <span class="px-1.5 py-0.5 rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm text-[8px]">ESC</span> Tutup
                    </div>
                </div>
            </div>
        </div>
    </template>
</header>