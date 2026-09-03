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

    <x-toast />
    @livewireScripts
    @stack('scripts')
    <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</body>

</html>
