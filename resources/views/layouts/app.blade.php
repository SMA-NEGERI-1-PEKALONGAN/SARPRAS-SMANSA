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
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} | {{ config('app.name', 'SARPRAS SMANSA') }}</title>

    {{-- Fonts & Icons --}}
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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.35.0/tabler-icons.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css"
        integrity="sha512-QeR2VH+lsBE5LSAe1Q5EnTBbe7XTBubt8dG93Y7gidSgdMCr8nVqKcfKAMyN96SV8KDbZVTDXChatu5G2KQGzg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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

        /* Custom scrollbar untuk sidebar */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        [x-cloak] {
            display: none !important;
        }

        /* Styling Container Select2 */
        .select2-container {
            width: 100% !important;
        }

        .select2-container .select2-selection--single {
            height: 48px !important;
            display: flex !important;
            align-items: center !important;
            border: 0 !important;
            border-radius: 0.75rem !important;
            background: #f8fafc !important;
        }

        .select2-container .select2-selection--single .select2-selection__rendered {
            padding: 0 3rem 0 1rem !important;
            line-height: normal !important;
            font-size: 0.875rem !important;
        }

        .select2-container .select2-selection--single .select2-selection__arrow {
            top: 50% !important;
            right: 0.875rem !important;
            transform: translateY(-50%) !important;
        }

        .select2-dropdown {
            z-index: 99999 !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 0.75rem !important;
            overflow: hidden !important;
        }

        .select2-container--default .select2-results__option {
            font-size: 0.875rem !important;
            padding: 0.75rem 1rem !important;
        }

        .select2-container--default .select2-search--dropdown {
            padding: 0.5rem !important;
        }

        .select2-container--default .select2-search--dropdown .select2-search__field {
            border-radius: 0.5rem !important;
            padding: 0.625rem 0.75rem !important;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background: #4f46e5 !important;
        }

        @media (max-width: 640px) {
            .select2-container .select2-selection--single {
                height: 46px !important;
            }

            .select2-container .select2-selection--single .select2-selection__rendered {
                font-size: 0.8125rem !important;
                padding-left: 0.875rem !important;
                padding-right: 2.5rem !important;
            }

            .select2-dropdown {
                width: calc(100vw - 24px) !important;
                max-width: calc(100vw - 24px) !important;
            }
        }

        .dark .select2-container .select2-selection--single {
            background: #1f2937 !important;
            color: #fff !important;
        }

        .dark .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #fff !important;
        }

        .dark .select2-dropdown {
            background: #111827 !important;
            border-color: #374151 !important;
        }

        .dark .select2-container--default .select2-results__option {
            background: #111827 !important;
            color: #fff !important;
        }

        .dark .select2-container--default .select2-search--dropdown {
            background: #111827 !important;
        }

        .dark .select2-container--default .select2-search--dropdown .select2-search__field {
            background: #1f2937 !important;
            border-color: #374151 !important;
            color: #fff !important;
        }

        /* Global Select Style */
        select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 100%;
            padding: 0.625rem 2.5rem 0.625rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
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
            border-color: #6366f1;
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
            border-color: #374151;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236b7280'%3E%3Cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3E%3C/svg%3E");
        }

        .dark select:hover {
            border-color: #4b5563;
            background-color: #4b5563;
        }

        .dark select:focus {
            border-color: #6366f1;
            background-color: #1f2937;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.25);
        }

        .dark select option {
            background-color: #1f2937;
            color: #f3f4f6;
        }

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

    </style>
    {{-- Theme Initializer --}}
    <script data-navigate-once>
        function applyTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
        applyTheme();
        document.addEventListener('livewire:navigated', applyTheme);

    </script>

    {{-- Alpine Stores --}}
    <script data-navigate-once>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                theme: localStorage.getItem('theme') || 'light',
                init() {
                    applyTheme();
                },
                toggle() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                    applyTheme();
                }
            });

            Alpine.store('sidebar', {
                isExpanded: localStorage.getItem('sidebar-expanded') !== 'false',
                isMobileOpen: false,
                init() {
                    const handleResize = () => {
                        if (window.innerWidth >= 1280) {
                            this.isMobileOpen = false;
                            this.isExpanded = localStorage.getItem('sidebar-expanded') !== 'false';
                        } else {
                            this.isExpanded = false;
                        }
                    };
                    window.addEventListener('resize', handleResize);
                    handleResize();
                },
                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    localStorage.setItem('sidebar-expanded', this.isExpanded);
                },
                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                }
            });
        });

    </script>
</head>

<body
    class="h-full antialiased text-gray-900 transition-colors duration-300 bg-gray-50 dark:bg-gray-950 dark:text-gray-100"
    x-data>

    <div class="flex min-h-screen">

        {{-- Mobile Overlay (Backdrop) --}}
        <div x-show="$store.sidebar.isMobileOpen" x-cloak x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" @click="$store.sidebar.toggleMobileOpen()"
            class="fixed inset-0 z-[55] bg-gray-900/50 backdrop-blur-sm xl:hidden">
        </div>

        {{-- Panggil Komponen Sidebar --}}
        <x-sidebar />

        {{-- Main Area --}}
        <div class="flex flex-col flex-1 min-w-0 min-h-screen transition-all duration-300 ease-in-out"
            :class="{'xl:ml-72': $store.sidebar.isExpanded, 'xl:ml-20': !$store.sidebar.isExpanded}">

            {{-- Panggil Komponen Header --}}
            <x-header />

            {{-- Page Content --}}
            <main class="flex-1 w-full min-w-0 p-4 md:p-8">
                <div class="w-full min-w-0 mx-auto max-w-screen-2xl">
                    {{ $slot }}
                </div>
            </main>

            <footer class="p-6 text-xs font-medium text-center text-gray-400">
                <p>&copy; {{ date('Y') }}, Built with ❤️ by <a href="#" class="font-bold hover:text-indigo-600">
                        Tim IT SMANSA</a></p>
            </footer>
        </div>
    </div>

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
    
    <x-toast />
    @livewireScripts
    @stack('scripts')
    <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <script data-navigate-once>
        document.addEventListener('alpine:init', () => {
           
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
