<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
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
        content="{{ csrf_token() }}"
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

    {{-- ===================================================== --}}
    {{-- Theme Initializer --}}
    {{-- ===================================================== --}}
    <script>
        (() => {
            const savedTheme = localStorage.getItem('theme');

            if (
                savedTheme === 'dark' ||
                (
                    !savedTheme &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches
                )
            ) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
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
    {{-- Navbar --}}
    {{-- ===================================================== --}}
    <header
        x-data="{ mobileMenuOpen: false }"
        class="sticky top-0 z-50
               bg-white/80 dark:bg-slate-900/80
               backdrop-blur-md
               border-b border-slate-100 dark:border-slate-800"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between h-20">

                {{-- ================================================= --}}
                {{-- Logo --}}
                {{-- ================================================= --}}
                <a
                    href="{{ route('home') }}"
                    class="flex items-center gap-3 group"
                >
                    <div
                        class="flex items-center justify-center
                               w-10 h-10 rounded-xl
                               bg-gradient-to-tr from-blue-600 to-indigo-500
                               text-white
                               shadow-lg shadow-blue-500/30
                               transition-transform duration-300
                               group-hover:scale-105"
                    >
                        <i class="text-xl font-bold fa-solid fa-door-open"></i>
                    </div>

                    <span
                        class="text-2xl font-bold
                               bg-gradient-to-r from-blue-700 to-indigo-600
                               dark:from-blue-500 dark:to-indigo-400
                               bg-clip-text text-transparent"
                    >
                        Ruang<span class="text-slate-800 dark:text-white">Ku</span>
                    </span>
                </a>

                {{-- ================================================= --}}
                {{-- Desktop Navigation --}}
                {{-- ================================================= --}}
                <nav class="items-center hidden gap-8 font-medium md:flex
                            text-slate-600 dark:text-slate-300">

                    <a
                        href="{{ route('home') }}"
                        class="flex items-center gap-2 transition-colors
                               {{ request()->routeIs('home')
                                    ? 'text-blue-600 dark:text-blue-400 font-semibold'
                                    : 'hover:text-blue-600 dark:hover:text-blue-400' }}"
                    >
                        <i class="text-sm fa-solid fa-house"></i>
                        Home
                    </a>

                    <a
                        href="{{ route('home') }}#alur"
                        class="flex items-center gap-2 transition-colors
                               hover:text-blue-600 dark:hover:text-blue-400"
                    >
                        <i class="text-sm fa-solid fa-list-check"></i>
                        Alur Peminjaman
                    </a>

                    <a
                        href="{{ route('home') }}#fitur"
                        class="flex items-center gap-2 transition-colors
                               hover:text-blue-600 dark:hover:text-blue-400"
                    >
                        <i class="text-sm fa-solid fa-star"></i>
                        Keunggulan
                    </a>

                    <a
                        href="{{ route('home') }}#faq"
                        class="flex items-center gap-2 transition-colors
                               hover:text-blue-600 dark:hover:text-blue-400"
                    >
                        <i class="text-sm fa-solid fa-circle-question"></i>
                        FAQ
                    </a>
                </nav>

                {{-- ================================================= --}}
                {{-- Desktop Actions --}}
                {{-- ================================================= --}}
                <div class="items-center hidden gap-3 md:flex">

                    {{-- Theme --}}
                    <button
                        type="button"
                        @click="$store.theme.toggle()"
                        class="p-2.5 rounded-xl
                               text-slate-600 dark:text-slate-300
                               hover:bg-slate-100 dark:hover:bg-slate-800
                               transition-all"
                        aria-label="Toggle Dark Mode"
                    >
                        <i
                            class="text-lg fa-solid"
                            :class="$store.theme.isDark ? 'fa-sun' : 'fa-moon'"
                        ></i>
                    </button>

                    @guest

                        <a
                            href="{{ route('login') }}"
                            class="px-5 py-2.5 rounded-xl
                                   font-medium
                                   text-slate-700 dark:text-slate-200
                                   hover:text-blue-600 dark:hover:text-blue-400
                                   hover:bg-slate-100 dark:hover:bg-slate-800
                                   transition-all"
                        >
                            Masuk
                        </a>

                        <a
                            href="{{ route('login') }}"
                            class="px-5 py-2.5 rounded-xl
                                   font-medium text-white
                                   bg-gradient-to-r from-blue-600 to-indigo-600
                                   hover:from-blue-700 hover:to-indigo-700
                                   shadow-lg shadow-blue-500/25
                                   transition-all"
                        >
                            Mulai Peminjaman
                            <i class="ml-1 fa-solid fa-arrow-right"></i>
                        </a>

                    @else

                        <a
                            href="{{ route('booking') }}"
                            class="px-5 py-2.5 rounded-xl
                                   font-medium text-white
                                   bg-gradient-to-r from-blue-600 to-indigo-600
                                   hover:from-blue-700 hover:to-indigo-700
                                   shadow-lg shadow-blue-500/25
                                   transition-all"
                        >
                            Booking Ruang
                            <i class="ml-1 fa-solid fa-arrow-right"></i>
                        </a>

                    @endguest

                </div>

                {{-- ================================================= --}}
                {{-- Mobile Buttons --}}
                {{-- ================================================= --}}
                <div class="flex items-center gap-2 md:hidden">

                    <button
                        type="button"
                        @click="$store.theme.toggle()"
                        class="p-2 rounded-xl
                               text-slate-600 dark:text-slate-300
                               hover:bg-slate-100 dark:hover:bg-slate-800"
                    >
                        <i
                            class="text-xl fa-solid"
                            :class="$store.theme.isDark ? 'fa-sun' : 'fa-moon'"
                        ></i>
                    </button>

                    <button
                        type="button"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="p-2 rounded-xl
                               text-slate-600 dark:text-slate-300
                               hover:bg-slate-100 dark:hover:bg-slate-800"
                    >
                        <i
                            class="text-2xl fa-solid"
                            :class="mobileMenuOpen ? 'fa-xmark' : 'fa-bars'"
                        ></i>
                    </button>

                </div>

            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- Mobile Navigation --}}
        {{-- ===================================================== --}}
        <div
            x-show="mobileMenuOpen"
            x-transition
            x-cloak
            class="px-4 pt-2 pb-6 space-y-3
                   border-b md:hidden
                   bg-white dark:bg-slate-900
                   border-slate-100 dark:border-slate-800"
        >

            <a
                href="{{ route('home') }}"
                @click="mobileMenuOpen = false"
                class="block px-4 py-2.5 rounded-lg
                       {{ request()->routeIs('home')
                            ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 font-semibold'
                            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800' }}"
            >
                <i class="mr-2 fa-solid fa-house"></i>
                Home
            </a>

            <a
                href="{{ route('home') }}#alur"
                @click="mobileMenuOpen = false"
                class="block px-4 py-2.5 rounded-lg
                       text-slate-600 dark:text-slate-300
                       hover:bg-slate-50 dark:hover:bg-slate-800"
            >
                <i class="mr-2 fa-solid fa-list-check"></i>
                Alur Peminjaman
            </a>

            <a
                href="{{ route('home') }}#fitur"
                @click="mobileMenuOpen = false"
                class="block px-4 py-2.5 rounded-lg
                       text-slate-600 dark:text-slate-300
                       hover:bg-slate-50 dark:hover:bg-slate-800"
            >
                <i class="mr-2 fa-solid fa-star"></i>
                Keunggulan
            </a>

            <a
                href="{{ route('home') }}#faq"
                @click="mobileMenuOpen = false"
                class="block px-4 py-2.5 rounded-lg
                       text-slate-600 dark:text-slate-300
                       hover:bg-slate-50 dark:hover:bg-slate-800"
            >
                <i class="mr-2 fa-solid fa-circle-question"></i>
                FAQ
            </a>

            <div
                class="pt-4 border-t
                       border-slate-100 dark:border-slate-800
                       flex flex-col gap-2"
            >

                @guest

                    <a
                        href="{{ route('login') }}"
                        class="w-full text-center px-4 py-2.5 rounded-xl
                               font-medium
                               text-slate-700 dark:text-slate-200
                               border border-slate-200 dark:border-slate-700"
                    >
                        Masuk
                    </a>

                    <a
                        href="{{ route('login') }}"
                        class="w-full text-center px-4 py-2.5 rounded-xl
                               font-medium text-white
                               bg-blue-600 hover:bg-blue-700"
                    >
                        Mulai Peminjaman
                    </a>

                @else

                    <a
                        href="{{ route('booking') }}"
                        class="w-full text-center px-4 py-2.5 rounded-xl
                               font-medium text-white
                               bg-blue-600 hover:bg-blue-700"
                    >
                        Booking Ruang
                    </a>

                @endguest

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
                        href="{{ route('home') }}"
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
                                href="{{ route('home') }}"
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
                                    href="{{ route('login') }}"
                                    class="transition-colors hover:text-white"
                                >
                                    Login
                                </a>
                            </li>

                        @else

                            <li>
                                <a
                                    href="{{ route('booking') }}"
                                    class="transition-colors hover:text-white"
                                >
                                    Booking Ruang
                                </a>
                            </li>

                            <li>
                                <a
                                    href="{{ route('history') }}"
                                    class="transition-colors hover:text-white"
                                >
                                    Riwayat Peminjaman
                                </a>
                            </li>

                            <li>
                                <a
                                    href="{{ route('account.settings') }}"
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