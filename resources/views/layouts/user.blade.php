@auth
    @php
        $notificationUnreadCount = \App\Models\SystemNotification::query()
            ->where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();

        $notifications = \App\Models\SystemNotification::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->limit(10)
            ->get();
    @endphp
@endauth
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
    
    {{-- pusher notification --}}
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    {{-- <meta name="theme-color" content="#2563eb"> --}}
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="SARPRAS SMANSA">
    <link rel="apple-touch-icon" href="{{ asset('img/icons/icon-192x192.png') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.35.0/tabler-icons.min.css" />

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


<body class="min-h-screen flex flex-col antialiased text-slate-800 dark:text-slate-200 bg-slate-50 dark:bg-slate-900 transition-colors duration-300">
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
    <footer class="pt-16 pb-8 mt-auto text-slate-400 bg-slate-900 dark:bg-slate-950 border-t border-slate-800">
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
                                   font-bold"
                        >
                            {{-- <i class="fa-solid fa-handshake"></i> --}}
                            <img src="{{ asset('img/logosmansa.png') }}" alt="Logo" class="h-9 w-9">
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
                    <div
                        x-data="{ canInstall: false }"
                        x-on:pwa-install-available.window="canInstall = true"
                        x-on:pwa-installed.window="canInstall = false"
                        x-show="canInstall"
                        x-cloak
                    >
                        <button
                            type="button"
                            @click="window.pwaInstall.install()"
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-blue-700"
                        >
                            <i class="fa-solid fa-download"></i>
                            Pasang Aplikasi
                        </button>
                    </div>
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
    
    {{-- pusher norification --}}
    <div x-data="pushNotificationPermission()" x-init="init()" x-show="showPermissionModal" x-cloak @keydown.escape.window="showPermissionModal = false" class="fixed inset-0 z-[9998] flex items-center justify-center p-3 sm:p-4">
        <div
            x-show="showPermissionModal"
            x-transition.opacity
            class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm"
        ></div>

        <div
            x-show="showPermissionModal"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-95"
            class="relative flex w-full max-w-md max-h-[calc(100dvh-1.5rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900 sm:max-h-[calc(100dvh-2rem)] sm:rounded-3xl"
        >
            <div class="overflow-y-auto overscroll-contain">
                <div class="p-4 sm:p-6 md:p-7">

                    <div class="flex h-14 w-14 items-center justify-center mx-auto rounded-2xl bg-blue-100 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 sm:h-16 sm:w-16">
                        <i class="text-xl sm:text-2xl fa-solid fa-bell"></i>
                    </div>

                    <div class="mt-4 text-center sm:mt-5">
                        <h2 class="text-lg font-bold leading-tight text-slate-900 dark:text-white sm:text-xl">
                            Aktifkan Notifikasi
                        </h2>

                        <p class="mx-auto mt-2 max-w-sm text-xs leading-relaxed text-slate-500 dark:text-slate-400 sm:text-sm">
                            Dapatkan pemberitahuan langsung ketika pengajuan peminjaman Anda disetujui, ditolak, atau selesai diproses.
                        </p>
                    </div>

                    <div class="mt-5 space-y-2.5 sm:mt-6 sm:space-y-3">

                        <div class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800 sm:rounded-2xl sm:p-3.5">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 sm:h-9 sm:w-9 sm:rounded-xl">
                                <i class="text-[11px] fa-solid fa-check"></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-bold leading-tight text-slate-800 dark:text-slate-200 sm:text-xs">
                                    Status peminjaman
                                </p>

                                <p class="mt-1 text-[9px] leading-relaxed text-slate-500 dark:text-slate-400 sm:text-[10px]">
                                    Anda akan mengetahui perubahan status tanpa harus membuka halaman riwayat.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800 sm:rounded-2xl sm:p-3.5">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 sm:h-9 sm:w-9 sm:rounded-xl">
                                <i class="text-[11px] fa-solid fa-mobile-screen-button"></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-bold leading-tight text-slate-800 dark:text-slate-200 sm:text-xs">
                                    Langsung ke perangkat
                                </p>

                                <p class="mt-1 text-[9px] leading-relaxed text-slate-500 dark:text-slate-400 sm:text-[10px]">
                                    Notifikasi dapat diterima di HP atau komputer yang telah diaktifkan.
                                </p>
                            </div>
                        </div>

                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-2.5 sm:mt-6 sm:grid-cols-2">

                        <button
                            type="button"
                            @click="decline()"
                            class="flex min-h-[44px] items-center justify-center rounded-xl bg-slate-100 px-4 py-3 text-xs font-bold text-slate-600 transition hover:bg-slate-200 active:scale-[0.98] dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 sm:text-sm"
                        >
                            Nanti
                        </button>

                        <button
                            type="button"
                            @click="enable()"
                            :disabled="loading"
                            class="flex min-h-[44px] items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-xs font-bold text-white shadow-lg shadow-blue-500/20 transition hover:bg-blue-700 active:scale-[0.98] disabled:cursor-wait disabled:opacity-60 sm:text-sm"
                        >
                            <span
                                x-show="!loading"
                                class="inline-flex items-center justify-center gap-1.5"
                            >
                                <i class="text-[11px] fa-solid fa-bell"></i>
                                <span>Aktifkan</span>
                            </span>

                            <span
                                x-show="loading"
                                class="inline-flex items-center justify-center gap-1.5"
                            >
                                <i class="text-[11px] fa-solid fa-spinner animate-spin"></i>
                                <span>Mengaktifkan...</span>
                            </span>
                        </button>

                    </div>

                    <div
                        x-show="message"
                        x-transition
                        class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-center text-[9px] font-medium leading-relaxed text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 sm:text-[10px]"
                    >
                        <span x-text="message"></span>
                    </div>

                    <div class="mt-4 text-center">
                        <p class="text-[8px] leading-relaxed text-slate-400 sm:text-[9px]">
                            Anda dapat mengubah izin notifikasi kapan saja melalui pengaturan browser.
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div x-data="{ open: false }" x-on:pwa-ios-install-available.window="open = true" x-on:pwa-ios-install-guide.window="open = true" x-on:pwa-installed.window="open = false" x-show="open" x-cloak class="fixed inset-0 z-[9997] flex items-center justify-center p-4">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm"></div>

        <div x-show="open" x-transition class="relative w-full max-w-sm overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
            <div class="p-5 sm:p-6">

                <div class="flex h-14 w-14 mx-auto items-center justify-center rounded-2xl bg-blue-100 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                    <i class="text-xl fa-solid fa-mobile-screen-button"></i>
                </div>

                <div class="mt-4 text-center">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                        Pasang Aplikasi
                    </h2>

                    <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        Pada iPhone, aplikasi dipasang melalui menu Bagikan di Safari.
                    </p>
                </div>

                <div class="mt-5 space-y-3">

                    <div class="flex items-start gap-3 rounded-2xl bg-slate-50 p-3 dark:bg-slate-800">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                            <span class="text-xs font-black">1</span>
                        </div>

                        <div class="min-w-0">
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                Tekan tombol Bagikan
                            </p>

                            <p class="mt-1 text-[10px] leading-relaxed text-slate-500 dark:text-slate-400">
                                Di Safari, tekan ikon
                                <i class="mx-0.5 fa-solid fa-arrow-up-from-bracket text-blue-500"></i>
                                Bagikan.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-2xl bg-slate-50 p-3 dark:bg-slate-800">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                            <span class="text-xs font-black">2</span>
                        </div>

                        <div class="min-w-0">
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                Pilih Tambah ke Layar Utama
                            </p>

                            <p class="mt-1 text-[10px] leading-relaxed text-slate-500 dark:text-slate-400">
                                Pilih <b>Tambah ke Layar Utama</b>.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-2xl bg-slate-50 p-3 dark:bg-slate-800">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400">
                            <span class="text-xs font-black">3</span>
                        </div>

                        <div class="min-w-0">
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                Buka sebagai Aplikasi Web
                            </p>

                            <p class="mt-1 text-[10px] leading-relaxed text-slate-500 dark:text-slate-400">
                                Aktifkan <b>Buka sebagai App Web</b> lalu tekan <b>Tambah</b>.
                            </p>
                        </div>
                    </div>

                </div>

                <button
                    type="button"
                    @click="open = false"
                    class="mt-5 flex min-h-[44px] w-full items-center justify-center rounded-xl bg-slate-100 px-4 py-3 text-xs font-bold text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                    Mengerti
                </button>

            </div>
        </div>
    </div>

    {{-- iphone modal --}}
    @livewireScripts

    @stack('scripts')
    <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        
    <script data-navigate-once>
        document.addEventListener('alpine:init', () => {
            // --- Theme Store ---
            Alpine.store('theme', {
                isDark: document.documentElement.classList.contains('dark'),

                toggle() {
                    this.isDark = !this.isDark;
                    localStorage.setItem('theme', this.isDark ? 'dark' : 'light');
                    document.documentElement.classList.toggle('dark', this.isDark);
                }
            });

            // --- Push Notification Component ---
            Alpine.data('pushNotificationPermission', () => ({
                showPermissionModal: false,
                loading: false,
                message: '',
                storageKey: 'sarpras-notification-prompt',

                async init() {
                    if (!@js(auth()->check())) return;
                    
                    // Di iPhone biasa (bukan dari home screen), PushManager belum tersedia. Ini normal.
                    if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) return;
                    if (Notification.permission === 'granted' || Notification.permission === 'denied') return;
                    if (localStorage.getItem(this.storageKey) === 'dismissed') return;

                    setTimeout(() => {
                        this.showPermissionModal = true;
                    }, 1200);
                },

                decline() {
                    localStorage.setItem(this.storageKey, 'dismissed');
                    this.showPermissionModal = false;
                },

                async enable() {
                    this.loading = true;
                    this.message = '';

                    try {
                        const permission = await Notification.requestPermission();

                        if (permission !== 'granted') {
                            localStorage.setItem(this.storageKey, 'dismissed');
                            this.showPermissionModal = false;
                            return;
                        }

                        const registration = await navigator.serviceWorker.ready;
                        const publicKey = @js(config('webpush.vapid.public_key') ?: env('VAPID_PUBLIC_KEY'));

                        if (!publicKey) {
                            throw new Error('VAPID public key belum dikonfigurasi.');
                        }

                        const subscription = await registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: this.urlBase64ToUint8Array(publicKey)
                        });

                        const key = subscription.getKey('p256dh');
                        const auth = subscription.getKey('auth');

                        if (!key || !auth) {
                            throw new Error('Subscription browser tidak lengkap.');
                        }

                        const response = await fetch('{{ route('push-subscriptions.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                endpoint: subscription.endpoint,
                                publicKey: this.arrayBufferToBase64(key),
                                authToken: this.arrayBufferToBase64(auth),
                                contentEncoding: 'aes128gcm'
                            })
                        });

                        if (!response.ok) {
                            throw new Error('Subscription gagal disimpan ke server.');
                        }

                        localStorage.setItem(this.storageKey, 'enabled');
                        this.showPermissionModal = false;
                        window.dispatchEvent(new CustomEvent('push-notification-enabled'));
                    } catch (error) {
                        console.error('Push notification error:', error);
                        this.message = error.message || 'Notifikasi gagal diaktifkan.';
                    } finally {
                        this.loading = false;
                    }
                },

                urlBase64ToUint8Array(base64String) {
                    const padding = '='.repeat((4 - base64String.length % 4) % 4);
                    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                    const rawData = window.atob(base64);
                    return Uint8Array.from([...rawData].map(char => char.charCodeAt(0)));
                },

                arrayBufferToBase64(buffer) {
                    return btoa(String.fromCharCode(...new Uint8Array(buffer)));
                }
            }));
        });

        // --- Service Worker Registration ---
        async function registerServiceWorker() {
            if (!('serviceWorker' in navigator)) return null;
            try {
                return await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            } catch (error) {
                return null;
            }
        }
        window.sarprasRegisterServiceWorker = registerServiceWorker;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', registerServiceWorker, { once: true });
        } else {
            registerServiceWorker();
        }
        document.addEventListener('livewire:navigated', registerServiceWorker);

        // --- Deteksi Lingkungan (iOS / Standalone) ---
        const isIos = () => {
            const userAgent = window.navigator.userAgent.toLowerCase();
            return /iphone|ipad|ipod/.test(userAgent);
        };
        
        // Apakah dibuka via Safari (bukan dari ikon Home Screen)
        const isIosBrowser = isIos() && !window.navigator.standalone; 

        // --- PWA Installation Logic ---
        if (!window.__sarprasPwaInitialized) {
            window.__sarprasPwaInitialized = true;

            window.pwaInstall = {
                deferredPrompt: null,

                init() {
                    // Event standard untuk Android / Chrome
                    window.addEventListener('beforeinstallprompt', event => {
                        event.preventDefault();
                        this.deferredPrompt = event;
                        window.dispatchEvent(new CustomEvent('pwa-install-available'));
                    });

                    window.addEventListener('appinstalled', () => {
                        this.deferredPrompt = null;
                        window.dispatchEvent(new CustomEvent('pwa-installed'));
                    });

                    // HACK UNTUK iOS: Beri tahu Alpine/Blade bahwa tombol install boleh dimunculkan
                    if (isIosBrowser) {
                        setTimeout(() => {
                            window.dispatchEvent(new CustomEvent('pwa-install-available'));
                        }, 500);
                    }
                },

                async install() {
                    // Jika user pakai iPhone, tampilkan instruksi manual karena Apple tidak mendukung prompt otomatis
                    if (isIosBrowser) {
                        // alert('Untuk menginstal aplikasi di iPhone:\n\n1. Tekan tombol Share (ikon kotak dengan panah ke atas) di menu bawah.\n2. Geser dan pilih "Add to Home Screen" (Tambahkan ke Layar Utama).');
                        // return;
                        window.dispatchEvent(
                            new CustomEvent('pwa-ios-install-available')
                        );
                    }

                    // Logic standard untuk Android
                    if (!this.deferredPrompt) return;
                    const promptEvent = this.deferredPrompt;
                    this.deferredPrompt = null;

                    await promptEvent.prompt();
                    await promptEvent.userChoice;
                }
            };

            window.pwaInstall.init();
        }

        // --- Check Standalone Display Mode ---
        var checkStandalone = () => {
            const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            if (standalone) {
                window.dispatchEvent(new CustomEvent('pwa-installed'));
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', checkStandalone, { once: true });
        } else {
            checkStandalone();
        }
    </script>
</body>
</html>