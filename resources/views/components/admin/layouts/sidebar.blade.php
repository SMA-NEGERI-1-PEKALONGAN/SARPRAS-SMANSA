{{-- Tambahkan @persist di sini untuk menjaga state Alpine.js antar navigasi halaman --}}
@persist('sidebar')
<aside id="sidebar"
    class="fixed inset-y-0 left-0 z-[999] bg-white/90 backdrop-blur-xl dark:bg-gray-950/90 text-gray-900 transition-all duration-500 ease-[cubic-bezier(0.4,0,0.2,1)] border-r border-gray-200/50 dark:border-gray-800/50 flex flex-col"
    x-data="{
        currentPath: window.location.pathname,
        openSubmenus: {},
        tooltipText: '',
        tooltipVisible: false,
        tooltipTop: 0,
        init() {
            // Dengarkan event navigasi Livewire untuk mengupdate path secara realtime
            document.addEventListener('livewire:navigated', () => {
                this.currentPath = window.location.pathname;
            });
        },
        get expanded() {
            if (window.innerWidth < 1280) return true;
            return $store.sidebar.isExpanded;
        },
        toggleSubmenu(key) { 
            if(!this.expanded) {
                $store.sidebar.toggleExpanded();
                setTimeout(() => { this.openSubmenus[key] = true; }, 100);
            } else {
                this.openSubmenus[key] = !this.openSubmenus[key]; 
            }
        },
        showTooltip(e, text) {
            if(!this.expanded) {
                this.tooltipText = text;
                this.tooltipVisible = true;
                this.tooltipTop = e.currentTarget.getBoundingClientRect().top + (e.currentTarget.offsetHeight / 2);
            }
        },
        isPathActive(path) {
            // Cek apakah URL sama persis
            if (this.currentPath === path) return true;
            
            // Cek apakah sedang berada di dalam sub-halaman (contoh: /admin/users/create)
            if (path !== '/' && path.length > 3) {
                return this.currentPath.startsWith(path + '/');
            }
            return false;
        },
        isGroupActive(paths) {
            // Mengecek apakah salah satu sub-menu path cocok dengan path saat ini
            return paths.some(path => this.isPathActive(path));
        }
    }"
    
    :class="{
        'w-72': expanded,
        'w-20': !expanded,
        '-translate-x-full xl:translate-x-0': !$store.sidebar.isMobileOpen,
        'translate-x-0': $store.sidebar.isMobileOpen,
    }"
    @mouseleave="tooltipVisible = false">

    <div x-show="tooltipVisible && !expanded" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-x-2"
         x-transition:enter-end="opacity-100 translate-x-0"
         class="fixed left-[90px] z-[10000] px-4 py-2.5 bg-gray-900 dark:bg-indigo-600 text-white text-[13px] font-bold rounded-xl pointer-events-none shadow-2xl min-w-[120px] whitespace-nowrap border border-white/10"
         :style="`top: ${tooltipTop}px; transform: translateY(-50%);`"
         x-cloak>
        
        <div class="flex items-center gap-2">
            <span x-text="tooltipText"></span>
        </div>

        <div class="absolute left-0 top-1/2 -translate-x-full -translate-y-1/2 w-0 h-0 border-y-[7px] border-y-transparent border-r-[7px] border-r-gray-900 dark:border-r-indigo-600"></div>
    </div>

    {{-- Logo Area --}}
    <div class="flex items-center p-4 overflow-hidden h-18 shrink-0">
        <a href="{{ route('admin.dashboard') }}" wire:navigate class="flex items-center gap-4 group shrink-0">
            <div class="relative w-12 h-12 bg-gradient-to-tr from-indigo-600 to-violet-500 rounded-2xl flex items-center justify-center transition-all duration-500 group-hover:rotate-[10deg] group-hover:scale-110">
                <i class="text-2xl text-white ti ti-shopping-cart"></i>
            </div>
            <div x-show="expanded" x-transition class="flex flex-col">
                <span class="text-lg font-bold leading-none dark:text-white">
                    SARPRAS<span class="text-indigo-600 "> SMANSA</span>
                </span>
                <span class="text-sm font-semibold text-gray-400">Admin Panel</span>
            </div>
        </a>
    </div>

    {{-- Navigation --}}
    <div class="flex-1 px-4 py-4 pb-4 overflow-y-auto no-scrollbar">
        <nav class="space-y-6">
            @foreach ($menuGroups as $gIdx => $group)
                <div class="space-y-2">
                    <h2 class="px-4 text-[11px] font-semibold text-gray-400 dark:text-gray-500 tracking-wider"
                        x-show="expanded" x-cloak>
                        {{ $group['title'] }}
                    </h2>
                    <ul class="space-y-1">
                        @foreach ($group['items'] as $iIdx => $item)
                            @php 
                                $key = "sub-$gIdx-$iIdx"; 
                                $subPaths = isset($item['subItems']) ? collect($item['subItems'])->map(fn($sub) => parse_url(route($sub['route']), PHP_URL_PATH) ?? '/')->toJson() : '[]';
                            @endphp
                            <li class="relative">
                                @if (isset($item['subItems']))
                                    <button @click="toggleSubmenu('{{ $key }}')"
                                        @mouseenter="showTooltip($event, '{{ $item['name'] }}')"
                                        class="w-full flex items-center py-2.5 rounded-xl transition-all duration-300 group relative"
                                        :class="[
                                            openSubmenus['{{ $key }}'] || isGroupActive({{ $subPaths }}) ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-900/50',
                                            expanded ? 'px-4' : 'justify-center'
                                        ]">
                                        
                                        <i class="ti ti-{{ $item['icon'] }} text-xl shrink-0 transition-transform duration-300 group-hover:scale-110"></i>
                                        
                                        <span x-show="expanded" x-cloak class="ml-3 text-sm font-medium text-left grow">
                                            {{ $item['name'] }}
                                        </span>

                                        <i x-show="expanded" x-cloak
                                            class="text-xs transition-transform duration-300 ti ti-chevron-right"
                                            :class="openSubmenus['{{ $key }}'] ? 'rotate-90' : ''"></i>
                                    </button>

                                    <div x-show="openSubmenus['{{ $key }}'] && expanded" 
                                         x-init="if(isGroupActive({{ $subPaths }})) openSubmenus['{{ $key }}'] = true"
                                         x-cloak x-collapse>
                                        <ul class="mt-1 ml-9 space-y-1 relative before:absolute before:left-[-12px] before:top-0 before:w-px before:h-full before:bg-gray-100 dark:before:bg-gray-800">
                                            @foreach ($item['subItems'] as $sub)
                                                @php
                                                    $subRoutePath = parse_url(route($sub['route']), PHP_URL_PATH) ?? '/';
                                                @endphp
                                                <li>
                                                    <a href="{{ route($sub['route']) }}" wire:navigate
                                                       class="relative flex items-center py-2 text-sm font-medium transition-colors duration-200"
                                                       :class="isPathActive('{{ $subRoutePath }}') ? 'text-indigo-600' : 'text-gray-400 hover:text-indigo-500'">
                                                        
                                                        <template x-if="isPathActive('{{ $subRoutePath }}')">
                                                            <div class="absolute left-[-14px] w-1 h-1 bg-indigo-600 rounded-full"></div>
                                                        </template>
                                                        
                                                        {{ $sub['name'] }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @else
                                    @php
                                        $routePath = parse_url(route($item['route']), PHP_URL_PATH) ?? '/';
                                    @endphp
                                    <a wire:navigate href="{{ route($item['route']) }}" 
                                       @mouseenter="showTooltip($event, '{{ $item['name'] }}')"
                                       class="flex items-center py-2.5 rounded-xl group relative overflow-hidden transition-all duration-300"
                                       :class="[
                                           isPathActive('{{ $routePath }}') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-900/50',
                                           expanded ? 'px-4' : 'justify-center'
                                       ]">
                                        
                                        <i class="ti ti-{{ $item['icon'] }} text-xl shrink-0 z-10 transition-transform duration-300 group-hover:scale-110"></i>
                                        
                                        <span x-show="expanded" x-cloak class="z-10 flex items-center ml-3 text-sm font-semibold grow">
                                            {{ $item['name'] }}
                                            @if(!empty($item['new']))
                                                <span class="ml-auto bg-rose-500 text-[9px] text-white px-1.5 py-0.5 rounded-md font-bold animate-pulse">New</span>
                                            @endif
                                        </span>
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>
    </div>

    {{-- Bottom Section --}}
    <div class="p-4 pb-6 space-y-3 border-t border-gray-100 dark:border-gray-800 shrink-0 bg-white/90 dark:bg-gray-950/90 xl:pb-4">
        <div class="flex items-center gap-1 p-1 bg-gray-100/50 dark:bg-gray-900/50 rounded-xl" x-show="expanded" x-cloak>
            <button @click="$store.theme.toggle()" class="flex items-center justify-center flex-1 gap-2 py-2 text-xs font-semibold transition-all rounded-lg"
                    :class="$store.theme.theme === 'light' ? 'bg-white shadow-sm text-indigo-600' : 'text-gray-400'">
                <i class="ti ti-sun"></i> Light
            </button>
            <button @click="$store.theme.toggle()" class="flex items-center justify-center flex-1 gap-2 py-2 text-xs font-semibold transition-all rounded-lg"
                    :class="$store.theme.theme === 'dark' ? 'bg-gray-800 text-indigo-400 shadow-sm' : 'text-gray-400'">
                <i class="ti ti-moon"></i> Dark
            </button>
        </div>

        <div class="relative p-3 transition-all border border-transparent cursor-pointer group bg-gray-50 dark:bg-gray-900/50 rounded-2xl hover:border-indigo-500/30"
             @click="!expanded ? $store.sidebar.toggleExpanded() : null">
            <div class="flex items-center gap-3" :class="expanded ? '' : 'justify-center'">
                <div class="relative shrink-0">
                    <img src="https://ui-avatars.com/api/?name={{ auth()->user()->name ?? 'User' }}&background=6366f1&color=fff" class="w-9 h-9 rounded-xl" alt="User">
                    <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-500 border-2 border-white dark:border-gray-950 rounded-full"></div>
                </div>
                <div x-show="expanded" x-cloak class="overflow-hidden">
                    <p class="text-xs font-bold text-gray-900 truncate dark:text-white">
                        {{ auth()->user()->name ?? 'User' }}
                    </p>
                    <p class="text-[10px] text-gray-400 truncate">{{ auth()->user()->role ?? 'Admin' }}</p>
                </div>
            </div>
        </div>
    </div>
</aside>
@endpersist