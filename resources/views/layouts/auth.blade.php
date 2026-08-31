<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Login' }} | {{ config('app.name', 'SARPRAS SMANSA') }}</title>

    {{-- Fonts & Icons --}}
    <link rel="icon" href="{{ asset('logosmansa.ico') }}" type="image/x-icon">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.35.0/tabler-icons.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
        .select2-container--default .select2-selection--single {
            background-color: #f9fafb !important;
            /* bg-gray-50 */
            border: none !important;
            border-radius: 0.75rem !important;
            /* rounded-xl */
            height: 42px !important;
            display: flex;
            align-items: center;
            padding-left: 0.5rem;
        }

        /* Warna Text Pilihan */
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #374151 !important;
            /* text-gray-700 */
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
            border: 1px solid #f3f4f6 !important;
            border-radius: 0.75rem !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
            overflow: hidden;
            z-index: 10005 !important;
            /* Pastikan di atas modal */
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
            background-color: #4f46e5 !important;
            /* bg-indigo-600 */
            color: white !important;
        }

        /* --- DARK MODE SUPPORT (Opsional, jika Anda pakai class 'dark' di body/html) --- */
        .dark .select2-container--default .select2-selection--single {
            background-color: #1f2937 !important;
            /* dark:bg-gray-800 */
        }

        .dark .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #f3f4f6 !important;
        }

        .dark .select2-dropdown {
            background-color: #1f2937 !important;
            border-color: #374151 !important;
        }

        .dark .select2-search--dropdown .select2-search__field {
            background-color: #111827 !important;
            border-color: #374151 !important;
            color: white !important;
        }

        .dark .select2-results__option {
            color: #d1d5db !important;
        }

    </style>
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
        });

    </script>

    <style>
        [x-cloak] {
            display: none !important;
        }

    </style>
</head>

<body class="h-full antialiased bg-white dark:bg-gray-950 selection:bg-indigo-100 dark:selection:bg-indigo-900/30">

    {{ $slot }}

    @livewireScripts
    @stack('scripts')
</body>

</html>
