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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta
        name="csrf-token"
        content="{{ csrf_token() }}" wire:navigate 
    >
    {{-- icon --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

    <title>
        {{ $title ?? 'SARPRAS SMANSA' }} |
        {{ config('app.name', 'SARPRAS SMANSA') }}
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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    {{-- ===================================================== --}}
    {{-- Application Assets --}}
    {{-- ===================================================== --}}
    @vite([
        'resources/css/guest.css',
        'resources/js/guest.js'
    ])

    @livewireStyles
    
    <style>
        
         html {
            /* indigo-600 == #E11D48 */
            --livewire-progress-bar-color: #387dee !important;
            z-index: 9999 !important;
        }

        [wire\:navigate-progress-bar] {
            height: 4px !important;
            box-shadow: 0 0 12px var(--livewire-progress-bar-color) !important;
            z-index: 9999 !important;
        }

        /* Hide scrollbar tetapi scrolling tetap aktif */
        /* .hide-scrollbar {
            -ms-overflow-style: none;  
            scrollbar-width: none;     
        }

        .hide-scrollbar::-webkit-scrollbar {
            display: none;             
        } */

         .hide-scrollbar {
            overflow: auto; /* Pastikan elemen bisa di-scroll */
            scrollbar-width: thin; /* Membuat scrollbar lebih tipis */
            scrollbar-color: #a0aec0 transparent; /* Warna thumb (pegangan) dan track (jalur) */
        }

        /* 1. Ukuran keseluruhan scrollbar */
        .hide-scrollbar::-webkit-scrollbar {
            width: 8px;  /* Lebar untuk scrollbar vertikal */
            height: 8px; /* Tinggi untuk scrollbar horizontal */
        }
        .hide-scrollbar::-webkit-scrollbar-track {
            background: transparent; /* Dibuat transparan agar menyatu dengan background */
            border-radius: 10px;
        }
        .hide-scrollbar::-webkit-scrollbar-thumb {
            background-color: #a0aec0; /* Warna abu-abu yang soft/modern */
            border-radius: 10px;       /* Membuat ujungnya membulat */
            border: 2px solid transparent; /* Trik untuk memberikan jarak (padding) pada thumb */
            background-clip: padding-box;
        }
        .hide-scrollbar::-webkit-scrollbar-thumb:hover {
            background-color: #718096; /* Warna berubah menjadi sedikit lebih gelap */
        }
        
        .select2-container--default .select2-selection--single {
            background-color: #ffffff    !important; /* bg-gray-50 */
            border: 1px solid #e5e7eb !important;
            border-radius: 0.75rem !important; /* rounded-xl */
            height: 42px !important;
            display: flex;
            align-items: center;
            padding-left: 0.5rem;
        }
        
        /* Warna Text Pilihan */
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #1f2937 !important; /* text-gray-700 */
            font-weight: 500;
            line-height: normal !important;
        }

        /* Panah Dropdown */
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
            right: 10px !important;
        }

        /* Styling Kotak Dropdown (List) */
        .select2-dropdown {
            border: 1px solid #e5e7eb !important;
            border-radius: 0.75rem !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
            overflow: hidden;
            z-index: 10005 !important; /* Pastikan di atas modal */
        }

        /* Kotak Pencarian di dalam Dropdown */
        .select2-search--dropdown .select2-search__field {
            border: 1px solid #e5e7eb !important;
            border-radius: 0.5rem !important;
            padding: 0.5rem !important;
            outline: none !important;
        }

        /* Item List */
        .select2-results__option {
            padding: 8px 16px !important;
            color: #4b5563 !important;
        }

        /* Item Saat Di-hover / Dipilih */
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
            background-color: #387dee !important; /* bg-indigo-600 */
            color: white !important;
        }
        /* salte slate-800 sama dengan #1f2937 */
        /* --- DARK MODE SUPPORT (Opsional, jika Anda pakai class 'dark' di body/html) --- */
        .dark .select2-container--default .select2-selection--single { background-color: #1f2937 !important; /* dark:bg-gray-800 */ }
        .dark .select2-container--default .select2-selection--single .select2-selection__rendered { color: #f3f4f6 !important; }
        .dark .select2-dropdown { background-color: #1f2937 !important; border-color: #374151 !important; }
        .dark .select2-search--dropdown .select2-search__field { background-color: #111827 !important; border-color: #374151 !important; color: white !important; }
        .dark .select2-results__option { color: #d1d5db !important; }

         /* Global Select Style */
        select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 100%;
            padding: 0.625rem 2.5rem 0.625rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #1d293d;
            background-color: rgba(249, 250, 251, 0.8);
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            outline: none;
            transition: all 0.2s ease-in-out;
            cursor: pointer;

            /* Custom SVG Arrow Icon */
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%239ca3af'%3E%3Cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1.25rem;
        }

        select:hover {
            border-color: #d1d5db;
            background-color: #ffffff;
        }

        select:focus {
            border-color: #387dee;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }

        select option {
            background-color: #ffffff;
            color: #1f2937;
        }

        /* Dark Mode Support (.dark class pada tag <html> atau <body>) */
        .dark select {
            color: #e5e7eb;
            background-color: rgba(31, 41, 55, 0.8);
            border-color: #1d293d;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236b7280'%3E%3Cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3E%3C/svg%3E");
        }

        .dark select:hover {
            border-color: #4b5563;
            background-color: #1f2937;
        }

        .dark select:focus {
            border-color: #387dee;
            background-color: #1f2937;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.25);
        }

        .dark select option {
            background-color: #1f2937;
            color: #f3f4f6;
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
    @include('components.user.layouts.header')

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
                            <i class="fa-solid fa-handshake"></i>
                        </div>

                        <span class="text-xl font-bold text-white">
                            Sarpras<span
                            class="text-blue-600 dark:text-blue-400
                                transition-colors duration-300"
                        >SMANSA</span>
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
                                    Peminjaman
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
                            info@sma1pekalongan.sch.id
                        </li>

                        <li class="flex items-center gap-2">
                            <i class="text-blue-500 fa-solid fa-phone"></i>
                            (0285) 421190
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
                    &copy; {{ date('Y') }} Tim IT SMANSA
                    All rights reserved.
                </p>

                <div class="flex gap-4 text-base">

                    <a
                        href="https://www.facebook.com/sman1pekalongan" target="_blank"
                        class="transition-colors hover:text-blue-400"
                    >
                        <i class="fa-brands fa-facebook"></i>
                    </a>

                    <a
                        href="https://x.com/SMA1PEKALONGAN" target="_blank"
                        class="transition-colors hover:text-blue-400"
                    >
                        <i class="fa-brands fa-x-twitter"></i>
                    </a>

                    <a
                        href="https://www.instagram.com/sma1pekalongan/"
                        class="transition-colors hover:text-blue-400" target="_blank"
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

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</body>
</html>