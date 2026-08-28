<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}" wire:navigate 
    class="scroll-smooth"
>
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}" wire:navigate 
    >

    <title>
        {{ $title ?? 'RuangKu' }} |
        {{ config('app.name', 'RuangKu') }}
    </title>

    {{-- ===================================================== --}}
    {{-- Fonts --}}
    {{-- ===================================================== --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    {{-- ===================================================== --}}
    {{-- Font Awesome --}}
    {{-- ===================================================== --}}
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css"
        integrity="sha512-QeR2VH+lsBE5LSAe1Q5EnTBbe7XTBubt8dG93Y7gidSgdMCr8nVqKcfKAMyN96SV8KDbZVTDXChatu5G2KQGzg=="
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
    >

    {{-- ===================================================== --}}
    {{-- Application Assets --}}
    {{-- ===================================================== --}}
    @vite([
        'resources/css/user.css',
        'resources/js/user.js'
    ])

    @livewireStyles
    <style>
        
         html {
            /* indigo-600 == #E11D48 */
            --livewire-progress-bar-color: #432dd7 !important;
            z-index: 9999 !important;
        }

        [wire\:navigate-progress-bar] {
            height: 4px !important;
            box-shadow: 0 0 12px var(--livewire-progress-bar-color) !important;
            z-index: 9999 !important;
        }
    </style>
    {{-- ===================================================== --}}
    {{-- Theme Initializer --}}
    {{-- ===================================================== --}}
    <script data-navigate-once>
        (() => {
            function applyTheme() {
                const savedTheme = localStorage.getItem('theme');

                // Jika belum ada preferensi tersimpan,
                // gunakan preferensi sistem.
                const isDark = savedTheme
                    ? savedTheme === 'dark'
                    : window.matchMedia('(prefers-color-scheme: dark)').matches;

                document.documentElement.classList.toggle('dark', isDark);
            }

            // Terapkan saat pertama kali halaman dimuat
            applyTheme();

            // Terapkan kembali setiap Livewire Navigate
            document.addEventListener('livewire:navigated', applyTheme);

            // Expose agar bisa digunakan Alpine Store
            window.applyTheme = applyTheme;
        })();
    </script>

    {{-- Alpine Stores --}}
    <script data-navigate-once>
        document.addEventListener('alpine:init', () => {

            Alpine.store('theme', {
                theme: localStorage.getItem('theme') || (
                    window.matchMedia('(prefers-color-scheme: dark)').matches
                        ? 'dark'
                        : 'light'
                ),

                init() {
                    window.applyTheme();
                },

                toggle() {
                    this.theme = this.theme === 'light'
                        ? 'dark'
                        : 'light';

                    localStorage.setItem('theme', this.theme);

                    window.applyTheme();
                }
            });

            Alpine.store('sidebar', {
                isExpanded: localStorage.getItem('sidebar-expanded') !== 'false',
                isMobileOpen: false,

                init() {
                    const handleResize = () => {
                        if (window.innerWidth >= 1280) {
                            this.isMobileOpen = false;
                            this.isExpanded =
                                localStorage.getItem('sidebar-expanded') !== 'false';
                        } else {
                            this.isExpanded = false;
                        }
                    };

                    window.addEventListener('resize', handleResize);

                    handleResize();
                },

                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;

                    localStorage.setItem(
                        'sidebar-expanded',
                        this.isExpanded
                    );
                },

                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                }
            });

        });
    </script>


    {{-- ===================================================== --}}
    {{-- Custom User Layout Style --}}
    {{-- ===================================================== --}}
    <style>
        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Poppins', sans-serif;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .dark .glass-card {
            background: rgba(30, 41, 59, 0.85);
            border-color: rgba(255, 255, 255, 0.1);
        }

        [x-cloak] {
            display: none !important;
        }

        ::selection {
            background: #3b82f6;
            color: #ffffff;
        }

        .animate-float {
            animation: ruangkufloat 4s ease-in-out infinite;
            will-change: transform;
        }

        @keyframes ruangkufloat {
            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .animate-float {
                animation: none;
            }
        }

        /* Hide scrollbar tetapi scrolling tetap aktif */
        .room-scroll { 
            scrollbar-width: none;
            -ms-overflow-style: none;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .room-scroll::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }
    </style>

    @stack('styles')
</head>

<body
    class="min-h-screen flex flex-col antialiased
           text-slate-800 dark:text-slate-200
           bg-slate-50 dark:bg-slate-900
           transition-colors duration-300"
>
    {{-- ===================================================== --}}
    {{-- Modern Responsive Navbar --}}
    {{-- ===================================================== --}}

    <header
        x-data="{
            mobileMenuOpen: false,
            scrolled: false,
            activeSection: 'home',
            observer: null,

            init() {
                this.checkScroll()
                this.setupSectionObserver()

                window.addEventListener('scroll', () => {
                    this.checkScroll()
                }, { passive: true })

                window.addEventListener('resize', () => {
                    this.setupSectionObserver()
                })
            },

            checkScroll() {
                this.scrolled = window.scrollY > 20
            },

            setupSectionObserver() {
                if (this.observer) {
                    this.observer.disconnect()
                }

                const sections = [
                    { id: 'hero', el: document.querySelector('#hero') },
                    { id: 'alur', el: document.querySelector('#alur') },
                    { id: 'fitur', el: document.querySelector('#fitur') },
                    { id: 'faq', el: document.querySelector('#faq') }
                ].filter(section => section.el)

                if (!sections.length) {
                    return
                }

                this.observer = new IntersectionObserver(
                    () => {
                        this.updateActiveSection()
                    },
                    {
                        rootMargin: '-72px 0px -35% 0px',
                        threshold: [0, 0.1, 0.25, 0.5, 0.75, 1]
                    }
                )

                sections.forEach(({ el }) => {
                    this.observer.observe(el)
                })

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

                if (window.scrollY <= 20) {
                    active = 'hero'
                }

                this.activeSection = active
            },

            setActive(section) {
                this.activeSection = section
                this.mobileMenuOpen = false
            },

            closeMenu() {
                this.mobileMenuOpen = false
            }
        }"

        @keydown.escape.window="mobileMenuOpen = false"

        :class="scrolled || mobileMenuOpen
            ? 'border-slate-200/80 bg-white/95 shadow-sm backdrop-blur-xl dark:border-slate-800/80 dark:bg-slate-950/95'
            : 'border-transparent bg-transparent shadow-none backdrop-blur-none'"

        class="fixed inset-x-0 top-0 z-50 border-b
            transition-all duration-300 ease-out"
    >
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            <div class="flex min-h-[72px] items-center justify-between gap-3 sm:gap-4">


                {{-- ================================================= --}}
                {{-- LOGO --}}
                {{-- ================================================= --}}

                <a
                    href="{{ route('home') }}"
                    wire:navigate
                    class="group flex shrink-0 items-center gap-2.5"
                    aria-label="RuangKu - Beranda"
                    @click="setActive('home')"
                >

                    {{-- Logo Icon --}}
                    <div
                        :class="scrolled || mobileMenuOpen
                            ? 'bg-gradient-to-br from-blue-600 to-indigo-600 shadow-md shadow-blue-500/20'
                            : 'bg-gradient-to-br from-blue-600 to-indigo-600 shadow-lg shadow-blue-500/20'"

                        class="flex h-10 w-10 items-center justify-center rounded-xl
                            text-white
                            transition-all duration-300
                            group-hover:-translate-y-0.5
                            group-hover:shadow-lg"
                    >
                        <i class="text-[17px] fa-solid fa-door-open"></i>
                    </div>


                    {{-- Brand --}}
                    <span
                        :class="scrolled || mobileMenuOpen
                            ? 'text-slate-900 dark:text-white'
                            : 'text-slate-900 dark:text-white'"

                        class="text-[19px] sm:text-[21px] font-bold tracking-tight
                            transition-colors duration-300"
                    >
                        Ruang<span
                            class="text-blue-600 dark:text-blue-400
                                transition-colors duration-300"
                        >Ku</span>
                    </span>

                </a>


                {{-- ================================================= --}}
                {{-- DESKTOP NAVIGATION --}}
                {{-- ================================================= --}}

                <nav
                    class="hidden items-center gap-1 xl:flex"
                    aria-label="Navigasi utama"
                >

                    {{-- HOME --}}
                    <a
                        href="{{ route('home') }}"
                        wire:navigate
                        @click="setActive('home')"

                        :class="activeSection === 'hero'
                            || activeSection === 'home'
                            ? (
                                scrolled
                                    ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'
                                    : 'bg-slate-900/5 text-slate-900 dark:bg-white/15 dark:text-white'
                            )
                            : (
                                scrolled
                                    ? 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'
                                    : 'text-slate-700 hover:bg-slate-900/5 hover:text-slate-900 dark:text-white/90 dark:hover:bg-white/10 dark:hover:text-white'
                            )"

                        class="group flex items-center gap-2 rounded-xl px-3.5 py-2
                            text-sm font-medium transition-all duration-300"
                    >
                        <i
                            class="text-[13px] transition-transform duration-200
                                group-hover:scale-110
                                fa-solid fa-house"
                        ></i>

                        <span>Home</span>
                    </a>


                    {{-- ALUR PEMINJAMAN --}}
                    <a
                        href="{{ route('home') }}#alur"

                        @click="setActive('alur')"

                        :class="activeSection === 'alur'
                            ? (
                                scrolled
                                    ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'
                                    : 'bg-slate-900/5 text-slate-900 dark:bg-white/15 dark:text-white'
                            )
                            : (
                                scrolled
                                    ? 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'
                                    : 'text-slate-700 hover:bg-slate-900/5 hover:text-slate-900 dark:text-white/90 dark:hover:bg-white/10 dark:hover:text-white'
                            )"

                        class="group flex items-center gap-2 rounded-xl px-3.5 py-2
                            text-sm font-medium transition-all duration-300"
                    >
                        <i
                            class="text-[13px] transition-transform duration-200
                                group-hover:scale-110
                                fa-solid fa-timeline"
                        ></i>

                        <span>Alur Peminjaman</span>
                    </a>


                    {{-- KEUNGGULAN --}}
                    <a
                        href="{{ route('home') }}#fitur"

                        @click="setActive('fitur')"

                        :class="activeSection === 'fitur'
                            ? (
                                scrolled
                                    ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'
                                    : 'bg-slate-900/5 text-slate-900 dark:bg-white/15 dark:text-white'
                            )
                            : (
                                scrolled
                                    ? 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'
                                    : 'text-slate-700 hover:bg-slate-900/5 hover:text-slate-900 dark:text-white/90 dark:hover:bg-white/10 dark:hover:text-white'
                            )"

                        class="group flex items-center gap-2 rounded-xl px-3.5 py-2
                            text-sm font-medium transition-all duration-300"
                    >
                        <i
                            class="text-[13px] transition-transform duration-200
                                group-hover:scale-110
                                fa-solid fa-lightbulb"
                        ></i>

                        <span>Keunggulan</span>
                    </a>


                    {{-- FAQ --}}
                    <a
                        href="{{ route('home') }}#faq"

                        @click="setActive('faq')"

                        :class="activeSection === 'faq'
                            ? (
                                scrolled
                                    ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'
                                    : 'bg-slate-900/5 text-slate-900 dark:bg-white/15 dark:text-white'
                            )
                            : (
                                scrolled
                                    ? 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'
                                    : 'text-slate-700 hover:bg-slate-900/5 hover:text-slate-900 dark:text-white/90 dark:hover:bg-white/10 dark:hover:text-white'
                            )"

                        class="group flex items-center gap-2 rounded-xl px-3.5 py-2
                            text-sm font-medium transition-all duration-300"
                    >
                        <i
                            class="text-[13px] transition-transform duration-200
                                group-hover:scale-110
                                fa-solid fa-circle-question"
                        ></i>

                        <span>FAQ</span>
                    </a>

                </nav>


                {{-- ================================================= --}}
                {{-- DESKTOP ACTIONS --}}
                {{-- ================================================= --}}

                <div class="hidden items-center gap-2 xl:flex">

                    {{-- Theme --}}
                    <button
                        type="button"
                        @click="$store.theme.toggle()"

                        :class="scrolled || mobileMenuOpen
                            ? 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'
                            : 'text-slate-700 hover:bg-slate-900/5 hover:text-slate-900 dark:text-white dark:hover:bg-white/10 dark:hover:text-white'"

                        class="group flex h-10 w-10 items-center justify-center
                            rounded-xl transition-all duration-300
                            focus:outline-none
                            focus:ring-2 focus:ring-blue-500/30"
                        aria-label="Ubah tema"
                        title="Ubah tema"
                    >
                        <i
                            class="text-[15px] transition-transform duration-300
                                group-hover:rotate-12 fa-solid"
                            :class="$store.theme.isDark ? 'fa-sun' : 'fa-moon'"
                        ></i>
                    </button>


                    {{-- Divider --}}
                    <div
                        :class="scrolled || mobileMenuOpen
                            ? 'bg-slate-200 dark:bg-slate-800'
                            : 'bg-slate-300/60 dark:bg-white/20'"

                        class="mx-1 h-7 w-px transition-colors duration-300"
                    ></div>


                    @guest

                        {{-- Login --}}
                        <a
                            href="{{ route('login') }}"
                            wire:navigate

                            :class="scrolled
                                ? 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white'
                                : 'text-slate-700 hover:bg-slate-900/5 hover:text-slate-900 dark:text-white dark:hover:bg-white/10'"

                            class="rounded-xl px-4 py-2.5 text-sm font-semibold
                                transition-all duration-300"
                        >
                            Masuk
                        </a>


                        {{-- CTA --}}
                        <a
                            href="{{ route('login') }}"
                            wire:navigate
                            class="group inline-flex items-center gap-2 rounded-xl
                                bg-gradient-to-r from-blue-600 to-indigo-600
                                px-4.5 py-2.5 text-sm font-semibold text-white
                                shadow-md shadow-blue-500/20
                                transition-all duration-300
                                hover:-translate-y-0.5
                                hover:shadow-lg hover:shadow-blue-500/25"
                        >
                            <span>Mulai Peminjaman</span>

                            <i
                                class="text-[11px] transition-transform duration-200
                                    group-hover:translate-x-0.5
                                    fa-solid fa-arrow-right"
                            ></i>
                        </a>

                    @else

                        {{-- Booking --}}
                        <a
                            href="{{ route('booking') }}"
                            wire:navigate
                            class="group inline-flex items-center gap-2 rounded-xl
                                bg-gradient-to-r from-blue-600 to-indigo-600
                                px-4.5 py-2.5 text-sm font-semibold text-white
                                shadow-md shadow-blue-500/20
                                transition-all duration-300
                                hover:-translate-y-0.5
                                hover:shadow-lg hover:shadow-blue-500/25"
                        >
                            <i class="text-[12px] fa-solid fa-calendar-plus"></i>

                            <span>Booking Ruang</span>

                            <i
                                class="text-[11px] transition-transform duration-200
                                    group-hover:translate-x-0.5
                                    fa-solid fa-arrow-right"
                            ></i>
                        </a>

                    @endguest

                </div>


                {{-- ================================================= --}}
                {{-- MOBILE ACTIONS --}}
                {{-- ================================================= --}}

                <div class="flex items-center gap-1 xl:hidden">

                    {{-- Theme --}}
                    <button
                        type="button"
                        @click="$store.theme.toggle()"

                        :class="scrolled || mobileMenuOpen
                            ? 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'
                            : 'text-slate-700 hover:bg-slate-900/5 hover:text-slate-900 dark:text-white dark:hover:bg-white/10 dark:hover:text-white'"

                        class="flex h-10 w-10 items-center justify-center
                            rounded-xl transition-all duration-300
                            focus:outline-none
                            focus:ring-2 focus:ring-blue-500/30"
                        aria-label="Ubah tema"
                    >
                        <i
                            class="text-[15px] fa-solid"
                            :class="$store.theme.isDark ? 'fa-sun' : 'fa-moon'"
                        ></i>
                    </button>


                    {{-- Mobile Menu --}}
                    <button
                        type="button"
                        @click="mobileMenuOpen = !mobileMenuOpen"

                        :class="scrolled || mobileMenuOpen
                            ? 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white'
                            : 'text-slate-700 hover:bg-slate-900/5 hover:text-slate-900 dark:text-white dark:hover:bg-white/10 dark:hover:text-white'"

                        class="flex h-10 w-10 items-center justify-center
                            rounded-xl transition-all duration-300
                            focus:outline-none
                            focus:ring-2 focus:ring-blue-500/30"
                        aria-label="Menu navigasi"
                        aria-controls="mobile-navigation"
                        :aria-expanded="mobileMenuOpen.toString()"
                    >
                        <i
                            class="text-lg transition-all duration-200 fa-solid"
                            :class="mobileMenuOpen
                                ? 'fa-xmark rotate-90'
                                : 'fa-bars'"
                        ></i>
                    </button>

                </div>

            </div>
        </div>


        {{-- ===================================================== --}}
        {{-- MOBILE NAVIGATION --}}
        {{-- ===================================================== --}}

        <div
            id="mobile-navigation"
            x-show="mobileMenuOpen"
            x-cloak

            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"

            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"

            class="xl:hidden"
        >

            <div
                class="border-t border-slate-100 bg-white px-4 pb-5 pt-3
                    shadow-xl shadow-slate-900/5
                    dark:border-slate-800 dark:bg-slate-950
                    dark:shadow-black/20 sm:px-6"
            >

                <nav
                    class="space-y-1"
                    aria-label="Navigasi mobile"
                >

                    {{-- HOME --}}
                    <a
                        href="{{ route('home') }}"
                        wire:navigate
                        @click="setActive('home')"

                        :class="activeSection === 'home' || activeSection === 'hero'
                            ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'
                            : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-900'"

                        class="flex items-center gap-3 rounded-xl px-3.5 py-3
                            text-sm font-medium transition-colors"
                    >
                        <span
                            :class="activeSection === 'home' || activeSection === 'hero'
                                ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400'
                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"

                            class="flex h-9 w-9 items-center justify-center rounded-lg
                                transition-colors"
                        >
                            <i class="text-sm fa-solid fa-house"></i>
                        </span>

                        <span class="flex-1">Home</span>

                        <i
                            x-show="activeSection === 'home' || activeSection === 'hero'"
                            x-cloak
                            class="text-xs fa-solid fa-check"
                        ></i>
                    </a>


                    {{-- ALUR --}}
                    <a
                        href="{{ route('home') }}#alur"
                        @click="setActive('alur')"

                        :class="activeSection === 'alur'
                            ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'
                            : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-900'"

                        class="flex items-center gap-3 rounded-xl px-3.5 py-3
                            text-sm font-medium transition-colors"
                    >
                        <span
                            :class="activeSection === 'alur'
                                ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400'
                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"

                            class="flex h-9 w-9 items-center justify-center rounded-lg
                                transition-colors"
                        >
                            <i class="text-sm fa-solid fa-timeline"></i>
                        </span>

                        <span class="flex-1">Alur Peminjaman</span>

                        <i
                            x-show="activeSection === 'alur'"
                            x-cloak
                            class="text-xs fa-solid fa-check"
                        ></i>
                    </a>


                    {{-- KEUNGGULAN --}}
                    <a
                        href="{{ route('home') }}#fitur"
                        @click="setActive('fitur')"

                        :class="activeSection === 'fitur'
                            ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'
                            : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-900'"

                        class="flex items-center gap-3 rounded-xl px-3.5 py-3
                            text-sm font-medium transition-colors"
                    >
                        <span
                            :class="activeSection === 'fitur'
                                ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400'
                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"

                            class="flex h-9 w-9 items-center justify-center rounded-lg
                                transition-colors"
                        >
                            <i class="text-sm fa-solid fa-lightbulb"></i>
                        </span>

                        <span class="flex-1">Keunggulan</span>

                        <i
                            x-show="activeSection === 'fitur'"
                            x-cloak
                            class="text-xs fa-solid fa-check"
                        ></i>
                    </a>


                    {{-- FAQ --}}
                    <a
                        href="{{ route('home') }}#faq"
                        @click="setActive('faq')"

                        :class="activeSection === 'faq'
                            ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'
                            : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-900'"

                        class="flex items-center gap-3 rounded-xl px-3.5 py-3
                            text-sm font-medium transition-colors"
                    >
                        <span
                            :class="activeSection === 'faq'
                                ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400'
                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"

                            class="flex h-9 w-9 items-center justify-center rounded-lg
                                transition-colors"
                        >
                            <i class="text-sm fa-solid fa-circle-question"></i>
                        </span>

                        <span class="flex-1">FAQ</span>

                        <i
                            x-show="activeSection === 'faq'"
                            x-cloak
                            class="text-xs fa-solid fa-check"
                        ></i>
                    </a>

                </nav>


                {{-- ================================================= --}}
                {{-- MOBILE CTA --}}
                {{-- ================================================= --}}

                <div
                    class="mt-4 border-t border-slate-100 pt-4
                        dark:border-slate-800"
                >

                    @guest

                        <div class="grid grid-cols-2 gap-2.5">

                            <a
                                href="{{ route('login') }}"
                                wire:navigate
                                @click="closeMenu()"
                                class="flex items-center justify-center rounded-xl
                                    border border-slate-200 bg-white
                                    px-4 py-3 text-sm font-semibold
                                    text-slate-700 transition-colors
                                    hover:bg-slate-50
                                    dark:border-slate-700 dark:bg-slate-900
                                    dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Masuk
                            </a>

                            <a
                                href="{{ route('login') }}"
                                wire:navigate
                                @click="closeMenu()"
                                class="flex items-center justify-center gap-2
                                    rounded-xl bg-gradient-to-r
                                    from-blue-600 to-indigo-600
                                    px-4 py-3 text-sm font-semibold text-white
                                    shadow-md shadow-blue-500/20
                                    transition-all duration-200
                                    hover:shadow-lg hover:shadow-blue-500/25"
                            >
                                <span>Mulai</span>

                                <i
                                    class="text-[10px] fa-solid fa-arrow-right"
                                ></i>
                            </a>

                        </div>

                    @else

                        <a
                            href="{{ route('booking') }}"
                            wire:navigate
                            @click="closeMenu()"
                            class="flex w-full items-center justify-center gap-2
                                rounded-xl bg-gradient-to-r
                                from-blue-600 to-indigo-600
                                px-4 py-3 text-sm font-semibold text-white
                                shadow-md shadow-blue-500/20
                                transition-all duration-200
                                hover:shadow-lg hover:shadow-blue-500/25"
                        >
                            <i
                                class="text-xs fa-solid fa-calendar-plus"
                            ></i>

                            <span>Booking Ruang</span>

                            <i
                                class="ml-0.5 text-[10px] fa-solid fa-arrow-right"
                            ></i>
                        </a>

                    @endguest

                </div>

            </div>
        </div>

    </header>


    {{-- ===================================================== --}}
    {{-- Main Content --}}
    {{-- ===================================================== --}}
    <main class="flex-1 w-full">
        {{ $slot }}
    </main>

    {{-- ===================================================== --}}
    {{-- Footer --}}
    {{-- ===================================================== --}}
    <footer
        class="pt-16 pb-8 mt-auto
               text-slate-400
               bg-slate-900 dark:bg-slate-950
               border-t border-slate-800"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div
                class="grid grid-cols-1 gap-10 pb-12
                       border-b md:grid-cols-4
                       border-slate-800"
            >

                {{-- Brand --}}
                <div class="space-y-4 md:col-span-1">

                    <a
                        href="{{ route('home') }}" wire:navigate 
                        class="flex items-center gap-3"
                    >
                        <div
                            class="flex items-center justify-center
                                   w-9 h-9 rounded-xl
                                   bg-blue-600
                                   text-white font-bold"
                        >
                            <i class="fa-solid fa-door-open"></i>
                        </div>

                        <span class="text-xl font-bold text-white">
                            RuangKu
                        </span>
                    </a>

                    <p class="text-xs leading-relaxed">
                        Sistem peminjaman ruangan digital terpadu
                        untuk efisiensi operasional dan pengelolaan
                        sarana prasarana modern.
                    </p>

                </div>

                {{-- Navigation --}}
                <div>

                    <h4
                        class="mb-4 text-sm font-semibold
                               tracking-wider text-white uppercase"
                    >
                        Navigasi
                    </h4>

                    <ul class="space-y-2.5 text-xs">

                        <li>
                            <a
                                href="{{ route('home') }}" wire:navigate 
                                class="transition-colors hover:text-white"
                            >
                                Home
                            </a>
                        </li>

                        <li>
                            <a
                                href="{{ route('home') }}#alur"
                                class="transition-colors hover:text-white"
                            >
                                Alur Peminjaman
                            </a>
                        </li>

                        <li>
                            <a
                                href="{{ route('home') }}#fitur"
                                class="transition-colors hover:text-white"
                            >
                                Keunggulan
                            </a>
                        </li>

                        <li>
                            <a
                                href="{{ route('home') }}#faq"
                                class="transition-colors hover:text-white"
                            >
                                FAQ
                            </a>
                        </li>

                    </ul>
                </div>

                {{-- User Menu --}}
                <div>

                    <h4
                        class="mb-4 text-sm font-semibold
                               tracking-wider text-white uppercase"
                    >
                        Akun
                    </h4>

                    <ul class="space-y-2.5 text-xs">

                        @guest

                            <li>
                                <a
                                    href="{{ route('login') }}" wire:navigate 
                                    class="transition-colors hover:text-white"
                                >
                                    Login
                                </a>
                            </li>

                        @else

                            <li>
                                <a
                                    href="{{ route('booking') }}" wire:navigate 
                                    class="transition-colors hover:text-white"
                                >
                                    Booking Ruang
                                </a>
                            </li>

                            <li>
                                <a
                                    href="{{ route('history') }}" wire:navigate 
                                    class="transition-colors hover:text-white"
                                >
                                    Riwayat Peminjaman
                                </a>
                            </li>

                            <li>
                                <a
                                    href="{{ route('account.settings') }}" wire:navigate 
                                    class="transition-colors hover:text-white"
                                >
                                    Setting Akun
                                </a>
                            </li>

                        @endguest

                    </ul>

                </div>

                {{-- Contact --}}
                <div>

                    <h4
                        class="mb-4 text-sm font-semibold
                               tracking-wider text-white uppercase"
                    >
                        Bantuan & Kontak
                    </h4>

                    <ul class="space-y-2.5 text-xs">

                        <li class="flex items-center gap-2">
                            <i class="text-blue-500 fa-solid fa-envelope"></i>
                            support@ruangku.sch.id
                        </li>

                        <li class="flex items-center gap-2">
                            <i class="text-blue-500 fa-solid fa-phone"></i>
                            +62 (021) 555-0199
                        </li>

                        <li class="flex items-center gap-2">
                            <i class="text-blue-500 fa-solid fa-location-dot"></i>
                            Sarpras / Administrasi
                        </li>

                    </ul>

                </div>

            </div>

            {{-- Copyright --}}
            <div
                class="flex flex-col items-center justify-between
                       gap-4 pt-8
                       text-xs text-slate-500
                       sm:flex-row"
            >
                <p>
                    &copy; {{ date('Y') }}
                    {{ config('app.name', 'RuangKu') }}.
                    All rights reserved.
                </p>

                <div class="flex gap-4 text-base">

                    <a
                        href="#"
                        class="transition-colors hover:text-blue-400"
                    >
                        <i class="fa-brands fa-facebook"></i>
                    </a>

                    <a
                        href="#"
                        class="transition-colors hover:text-blue-400"
                    >
                        <i class="fa-brands fa-twitter"></i>
                    </a>

                    <a
                        href="#"
                        class="transition-colors hover:text-blue-400"
                    >
                        <i class="fa-brands fa-instagram"></i>
                    </a>

                </div>
            </div>

        </div>
    </footer>

    {{-- ===================================================== --}}
    {{-- Alpine Global Store --}}
    {{-- ===================================================== --}}
    <script>
        document.addEventListener('alpine:init', () => {

            Alpine.store('theme', {

                isDark:
                    document.documentElement.classList.contains('dark'),

                toggle() {

                    this.isDark = !this.isDark;

                    localStorage.setItem(
                        'theme',
                        this.isDark ? 'dark' : 'light'
                    );

                    if (this.isDark) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                }

            });

        });
    </script>

    @livewireScripts

    @stack('scripts')

</body>
</html>