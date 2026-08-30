<header x-data="{
        mobileMenuOpen: false,
        profileMenuOpen: false,
        scrolled: false,
        activeSection: 'home',
        observer: null,
        init() {
            this.checkScroll()
            this.setupSectionObserver()
            window.addEventListener('scroll', () => this.checkScroll(), { passive: true })
            window.addEventListener('resize', () => this.setupSectionObserver())
        },
        checkScroll() {
            this.scrolled = window.scrollY > 20
        },
        setupSectionObserver() {
            if (this.observer) this.observer.disconnect()
            const sections = [
                { id: 'hero', el: document.querySelector('#hero') },
                { id: 'alur', el: document.querySelector('#alur') },
                { id: 'fitur', el: document.querySelector('#fitur') },
                { id: 'faq', el: document.querySelector('#faq') }
            ].filter(section => section.el)
            if (!sections.length) return
            this.observer = new IntersectionObserver(() => this.updateActiveSection(), {
                rootMargin: '-72px 0px -35% 0px',
                threshold: [0, 0.1, 0.25, 0.5, 0.75, 1]
            })
            sections.forEach(({ el }) => this.observer.observe(el))
            this.updateActiveSection()
        },
        updateActiveSection() {
            const sections = [
                { id: 'hero', el: document.querySelector('#hero') },
                { id: 'alur', el: document.querySelector('#alur') },
                { id: 'fitur', el: document.querySelector('#fitur') },
                { id: 'faq', el: document.querySelector('#faq') }
            ].filter(section => section.el)
            if (!sections.length) return
            const marker = 72 + (window.innerHeight * 0.16)
            let active = sections[0].id
            let closestDistance = Infinity
            sections.forEach(({ id, el }) => {
                const rect = el.getBoundingClientRect()
                if (rect.top <= marker && rect.bottom > 72) {
                    const distance = Math.abs(rect.top - marker)
                    if (distance < closestDistance) {
                        closestDistance = distance
                        active = id
                    }
                }
            })
            if (window.scrollY <= 20) active = 'hero'
            this.activeSection = active
        },
        setActive(section) {
            this.activeSection = section
            this.mobileMenuOpen = false
            this.profileMenuOpen = false
        },
        closeMenu() {
            this.mobileMenuOpen = false
            this.profileMenuOpen = false
        }
    }" @keydown.escape.window="mobileMenuOpen = false; profileMenuOpen = false" :class="scrolled || mobileMenuOpen || profileMenuOpen
        ? 'border-slate-200/80 bg-white/95 shadow-sm backdrop-blur-xl dark:border-slate-800/80 dark:bg-slate-950/95'
        : 'border-transparent bg-transparent shadow-none backdrop-blur-none'"
    class="fixed inset-x-0 top-0 z-50 border-b transition-all duration-300 ease-out">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex min-h-[72px] items-center justify-between gap-3 sm:gap-4">
            <a href="{{ route('home') }}" wire:navigate class="group flex shrink-0 items-center gap-2.5"
                aria-label="Beranda - SARPRAS SMANSA" @click="setActive('home')">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/20 transition-all duration-300 group-hover:-translate-y-0.5 group-hover:shadow-lg">
                    <i class="text-[17px] fa-solid fa-handshake"></i>
                </div>
                <span
                    class="text-[19px] font-bold tracking-tight text-slate-900 transition-colors duration-300 sm:text-[21px] dark:text-white">
                    Sarpras<span class="text-blue-600 dark:text-blue-400">SMANSA</span>
                </span>
            </a>
            <nav class="hidden items-center gap-1 xl:flex" aria-label="Navigasi utama">
                @guest
                <a href="{{ route('home') }}" wire:navigate @click="setActive('home')"
                    :class="activeSection === 'hero' || activeSection === 'home' ? (scrolled ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'bg-slate-900/5 text-slate-900 dark:bg-white/15 dark:text-white') : (scrolled ? 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' : 'text-slate-700 hover:bg-slate-900/5 hover:text-slate-900 dark:text-white/90 dark:hover:bg-white/10 dark:hover:text-white')"
                    class="group flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition-all duration-300">
                    <i
                        class="text-[13px] transition-transform duration-200 group-hover:scale-110 fa-solid fa-house"></i>
                    <span>Home</span>
                </a>
                <a href="{{ route('home') }}#alur" @click="setActive('alur')"
                    :class="activeSection === 'alur' ? (scrolled ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'bg-slate-900/5 text-slate-900 dark:bg-white/15 dark:text-white') : (scrolled ? 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' : 'text-slate-700 hover:bg-slate-900/5 hover:text-slate-900 dark:text-white/90 dark:hover:bg-white/10 dark:hover:text-white')"
                    class="group flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition-all duration-300">
                    <i
                        class="text-[13px] transition-transform duration-200 group-hover:scale-110 fa-solid fa-timeline"></i>
                    <span>Alur Peminjaman</span>
                </a>
                <a href="{{ route('home') }}#fitur" @click="setActive('fitur')"
                    :class="activeSection === 'fitur' ? (scrolled ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'bg-slate-900/5 text-slate-900 dark:bg-white/15 dark:text-white') : (scrolled ? 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' : 'text-slate-700 hover:bg-slate-900/5 hover:text-slate-900 dark:text-white/90 dark:hover:bg-white/10 dark:hover:text-white')"
                    class="group flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition-all duration-300">
                    <i
                        class="text-[13px] transition-transform duration-200 group-hover:scale-110 fa-solid fa-lightbulb"></i>
                    <span>Keunggulan</span>
                </a>
                <a href="{{ route('home') }}#faq" @click="setActive('faq')"
                    :class="activeSection === 'faq' ? (scrolled ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'bg-slate-900/5 text-slate-900 dark:bg-white/15 dark:text-white') : (scrolled ? 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' : 'text-slate-700 hover:bg-slate-900/5 hover:text-slate-900 dark:text-white/90 dark:hover:bg-white/10 dark:hover:text-white')"
                    class="group flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition-all duration-300">
                    <i
                        class="text-[13px] transition-transform duration-200 group-hover:scale-110 fa-solid fa-circle-question"></i>
                    <span>FAQ</span>
                </a>
                @else
                <a href="{{ route('home') }}" wire:navigate
                    class="flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition-all duration-300 {{ request()->routeIs('home') ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                    <i class="text-[13px] fa-solid fa-house"></i>
                    <span>Home</span>
                </a>
                <a href="{{ route('booking') }}" wire:navigate
                    class="flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition-all duration-300 {{ request()->routeIs('booking') ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                    <i class="text-[13px] fa-solid fa-calendar-check"></i>
                    <span>Peminjaman</span>
                </a>
                <a href="{{ route('history') }}" wire:navigate
                    class="flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition-all duration-300 {{ request()->routeIs('history') ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                    <i class="text-[13px] fa-solid fa-clock-rotate-left"></i>
                    <span>Riwayat</span>
                </a>
                @endguest
            </nav>

            <div class="hidden items-center gap-2 xl:flex">
                <button type="button" @click="$store.theme.toggle()"
                    :class="scrolled || mobileMenuOpen || profileMenuOpen ? 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' : 'text-slate-700 hover:bg-slate-900/5 hover:text-slate-900 dark:text-white dark:hover:bg-white/10 dark:hover:text-white'"
                    class="group flex h-10 w-10 items-center justify-center rounded-xl transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                    aria-label="Ubah tema">
                    <i class="text-[15px] transition-transform duration-300 group-hover:rotate-12 fa-solid"
                        :class="$store.theme.isDark ? 'fa-sun' : 'fa-moon'"></i>
                </button>

                @guest
                <a href="{{ route('login') }}" wire:navigate
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-700 transition-all duration-300 hover:bg-slate-100 hover:text-slate-900 dark:text-white dark:hover:bg-slate-800">Masuk</a>
                <a href="{{ route('booking') }}" wire:navigate
                    class="group inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-blue-500/20 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-blue-500/25">
                    <span>Mulai Peminjaman</span>
                    <i
                        class="text-[11px] transition-transform duration-200 group-hover:translate-x-0.5 fa-solid fa-arrow-right"></i>
                </a>
                @else
                <button type="button"
                    class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition-all hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                    aria-label="Notifikasi">
                    <i class="text-[15px] fa-regular fa-bell"></i>
                    <span
                        class="absolute right-2 top-2 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-white dark:ring-slate-950"></span>
                </button>

                <div class="mx-1 h-7 w-px bg-slate-200 dark:bg-slate-800"></div>

                <div class="relative" @click.outside="profileMenuOpen = false">
                    <button type="button" @click="profileMenuOpen = !profileMenuOpen"
                        class="flex items-center gap-2 rounded-xl p-1.5 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name) }}&background=dbeafe&color=1e3a8a&bold=true"
                            alt="Profile" class="h-8 w-8 rounded-lg object-cover">
                        <div class="hidden text-left lg:block">
                            <p
                                class="max-w-[120px] truncate text-xs font-bold leading-tight text-slate-800 dark:text-white">
                                {{ auth()->user()->name }}</p>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400">
                                {{ auth()->user()->role ?? 'User' }}</p>
                        </div>
                        <i class="text-[10px] text-slate-400 transition-transform duration-200 fa-solid fa-chevron-down"
                            :class="profileMenuOpen ? 'rotate-180' : ''"></i>
                    </button>

                    <div x-show="profileMenuOpen" x-cloak x-transition
                        class="absolute right-0 top-full z-50 mt-2 w-56 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                            <p class="truncate text-xs font-bold text-slate-900 dark:text-white">
                                {{ auth()->user()->name }}</p>
                            <p class="mt-0.5 truncate text-[10px] text-slate-500 dark:text-slate-400">
                                {{ auth()->user()->email }}</p>
                        </div>
                        <div class="p-2">
                            {{-- <a href="{{ route('account.settings') }}" wire:navigate @click="profileMenuOpen =
                            false" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                            text-slate-700 transition-colors hover:bg-slate-50 dark:text-slate-200
                            dark:hover:bg-slate-800">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"><i
                                    class="fa-solid fa-user"></i></span>
                            <span>Profil</span>
                            </a> --}}
                            <a href="{{ route('account.settings') }}" wire:navigate @click="profileMenuOpen = false"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"><i
                                        class="fa-solid fa-gear"></i></span>
                                <span>Pengaturan Akun</span>
                            </a>

                            <a href="{{ route('logout') }}" wire:navigate
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-rose-600 transition-colors hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-900/20">
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 dark:bg-rose-900/20"><i
                                        class="fa-solid fa-right-from-bracket"></i></span>
                                <span>Logout</span></a>
                        </div>
                    </div>
                </div>
                @endguest
            </div>

            <div class="flex items-center gap-1 xl:hidden">
                <button type="button" @click="$store.theme.toggle()"
                    class="flex h-10 w-10 items-center justify-center rounded-xl text-slate-700 transition-all hover:bg-slate-100 dark:text-white dark:hover:bg-slate-800"
                    aria-label="Ubah tema">
                    <i class="text-[15px] fa-solid" :class="$store.theme.isDark ? 'fa-sun' : 'fa-moon'"></i>
                </button>
                @auth
                <button type="button"
                    class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition-all hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                    aria-label="Notifikasi">
                    <i class="text-[15px] fa-regular fa-bell"></i>
                    <span
                        class="absolute right-2 top-2 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-white dark:ring-slate-950"></span>
                </button>
                @endauth
                <button type="button" @click="mobileMenuOpen = !mobileMenuOpen"
                    :class="mobileMenuOpen ? 'text-slate-700 dark:text-white' : 'text-slate-700 dark:text-white'"
                    class="flex h-10 w-10 items-center justify-center rounded-xl transition-all hover:bg-slate-100 dark:hover:bg-slate-800"
                    aria-label="Menu navigasi">
                    <i class="text-lg transition-all duration-200 fa-solid"
                        :class="mobileMenuOpen ? 'fa-xmark rotate-90' : 'fa-bars'"></i>
                </button>
            </div>
        </div>
    </div>

    <div id="mobile-navigation" x-show="mobileMenuOpen" x-cloak x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2" class="xl:hidden">
        <div
            class="border-t border-slate-100 bg-white px-4 pb-5 pt-3 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-950 dark:shadow-black/20 sm:px-6">
            <nav class="space-y-1" aria-label="Navigasi mobile">
                @guest
                <a href="{{ route('home') }}" wire:navigate @click="setActive('home')"
                    :class="activeSection === 'hero' || activeSection === 'home' ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-700 dark:text-slate-200'"
                    class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition-colors">
                    <span
                        :class="activeSection === 'hero' || activeSection === 'home' ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                        class="flex h-9 w-9 items-center justify-center rounded-lg transition-colors">
                        <i class="text-sm fa-solid fa-house"></i>
                    </span>
                    <span class="flex-1">Home</span>
                </a>
                <a href="{{ route('home') }}#alur" @click="setActive('alur')"
                    :class="activeSection === 'alur' ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-700 dark:text-slate-200'"
                    class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition-colors">
                    <span
                        :class="activeSection === 'alur' ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                        class="flex h-9 w-9 items-center justify-center rounded-lg transition-colors">
                        <i class="text-sm fa-solid fa-timeline"></i>
                    </span>
                    <span class="flex-1">Alur Peminjaman</span>
                </a>

                <a href="{{ route('home') }}#fitur" @click="setActive('fitur')"
                    :class="activeSection === 'fitur' ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-700 dark:text-slate-200'"
                    class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition-colors">
                    <span
                        :class="activeSection === 'fitur' ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                        class="flex h-9 w-9 items-center justify-center rounded-lg transition-colors">
                        <i class="text-sm fa-solid fa-lightbulb"></i>
                    </span>
                    <span class="flex-1">Keunggulan</span>
                </a>

                <a href="{{ route('home') }}#faq" @click="setActive('faq')"
                    :class="activeSection === 'faq' ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-700 dark:text-slate-200'"
                    class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition-colors">
                    <span
                        :class="activeSection === 'faq' ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                        class="flex h-9 w-9 items-center justify-center rounded-lg transition-colors">
                        <i class="text-sm fa-solid fa-circle-question"></i>
                    </span>
                    <span class="flex-1">FAQ</span>
                </a>
                @else
                <a href="{{ route('home') }}" wire:navigate @click="closeMenu()"
                    class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition-colors {{ request()->routeIs('home') ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-900' }}">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg {{ request()->routeIs('home') ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                        <i class="text-sm fa-solid fa-house"></i>
                    </span>
                    <span class="flex-1">Home</span>
                </a>

                <a href="{{ route('booking') }}" wire:navigate @click="closeMenu()"
                    class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition-colors {{ request()->routeIs('booking') ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-900' }}">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg {{ request()->routeIs('booking') ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                        <i class="text-sm fa-solid fa-calendar-check"></i>
                    </span>
                    <span class="flex-1">Peminjaman</span>
                </a>

                <a href="{{ route('history') }}" wire:navigate @click="closeMenu()"
                    class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition-colors {{ request()->routeIs('history') ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-900' }}">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg {{ request()->routeIs('history') ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                        <i class="text-sm fa-solid fa-clock-rotate-left"></i>
                    </span>
                    <span class="flex-1">Riwayat</span>
                </a>

                <div class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800">
                    <div class="mb-2 flex items-center gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-900">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name) }}&background=dbeafe&color=1e3a8a&bold=true"
                            alt="Profile" class="h-10 w-10 rounded-xl object-cover">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-bold text-slate-900 dark:text-white">
                                {{ auth()->user()->name }}</p>
                            <p class="truncate text-[10px] text-slate-500 dark:text-slate-400">
                                {{ auth()->user()->email }}</p>
                        </div>
                    </div>

                    <a href="{{ route('account.settings') }}" wire:navigate @click="closeMenu()"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-900">
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            <i class="text-sm fa-solid fa-gear"></i>
                        </span>
                        <span class="flex-1">Pengaturan Akun</span>
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="flex w-full items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium text-rose-600 transition-colors hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-900/20">
                            <span
                                class="flex h-9 w-9 items-center justify-center rounded-lg bg-rose-50 dark:bg-rose-900/20">
                                <i class="text-sm fa-solid fa-right-from-bracket"></i>
                            </span>
                            <span class="flex-1 text-left">Logout</span>
                        </button>
                    </form>
                </div>
                @endguest
            </nav>

            @guest
            <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                <div class="grid grid-cols-2 gap-2.5">
                    <a href="{{ route('login') }}" wire:navigate @click="closeMenu()"
                        class="flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        Masuk
                    </a>
                    <a href="{{ route('booking') }}" wire:navigate @click="closeMenu()"
                        class="flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-md shadow-blue-500/20 transition-all hover:shadow-lg hover:shadow-blue-500/25">
                        <span>Mulai</span>
                        <i class="text-[10px] fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            @endguest
        </div>
    </div>
</header>
