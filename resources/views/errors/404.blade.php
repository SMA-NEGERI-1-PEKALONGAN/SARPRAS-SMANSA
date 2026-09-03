<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Halaman Tidak Ditemukan - RuangKu</title>
    
    <!-- Script to prevent FOUC on Dark Mode -->
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#1e3a8a',
                        }
                    },
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-20px)' },
                        }
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 antialiased min-h-screen flex flex-col transition-colors duration-300 relative overflow-hidden">

    <!-- Decorative Background Elements -->
    <div class="absolute top-1/4 left-10 w-72 h-72 bg-brand-500/10 dark:bg-brand-500/20 rounded-full mix-blend-multiply dark:mix-blend-lighten filter blur-3xl opacity-70 animate-float"></div>
    <div class="absolute bottom-1/4 right-10 w-96 h-96 bg-indigo-500/10 dark:bg-indigo-500/20 rounded-full mix-blend-multiply dark:mix-blend-lighten filter blur-3xl opacity-70 animate-float" style="animation-delay: -3s;"></div>

    <!-- Top Navigation (Simplified) -->
    <header class="absolute top-0 w-full z-40 bg-transparent">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 sm:h-20">
                <!-- Logo -->
                <a href="{{ route('home') }}" wire:navigate class="group flex shrink-0 items-center gap-2.5"
                    aria-label="Beranda - SARPRAS SMANSA" @click="setActive('home')">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl  text-white  transition-all duration-300 group-hover:-translate-y-0.5 group-hover:shadow-lg">
                        {{-- <i class="text-[17px] fa-solid fa-handshake"></i> --}}
                        <img src="{{ asset('img/logosmansa.png') }}" alt="Logo" class="h-10 w-10">
                    </div>
                    <span
                        class="text-[19px] font-bold tracking-tight text-slate-900 transition-colors duration-300 sm:text-[21px] dark:text-white">
                        Sarpras<span class="text-blue-600 dark:text-blue-400">SMANSA</span>
                    </span>
                </a>

                <!-- Theme Toggle -->
                <button id="theme-toggle" class="p-2 rounded-xl text-slate-500 hover:bg-white/50 dark:text-slate-400 dark:hover:bg-slate-800/50 backdrop-blur-sm transition-all focus:outline-none">
                    <i id="theme-icon" class="fa-solid fa-moon"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col items-center justify-center relative z-10 px-4 sm:px-6 lg:px-8 py-20 text-center">
        
        <!-- 404 Illustration Area -->
        <div class="relative w-full max-w-lg mx-auto mb-8 animate-float">
            <!-- Large 404 Text Background -->
            <div class="text-[150px] sm:text-[200px] font-black leading-none text-slate-200/50 dark:text-slate-800/50 select-none absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 -z-10">
                404
            </div>
            
            <!-- Main Icon/Illustration -->
            <div class="relative w-48 h-48 sm:w-64 sm:h-64 mx-auto bg-white dark:bg-slate-800 rounded-full shadow-2xl border-4 border-slate-50 dark:border-slate-700/50 flex items-center justify-center">
                <div class="absolute inset-0 rounded-full border-4 border-brand-500/30 dark:border-brand-400/20 border-dashed animate-[spin_10s_linear_infinite]"></div>
                
                <div class="text-brand-500 dark:text-brand-400 text-6xl sm:text-8xl relative z-10">
                    <i class="fa-solid fa-ghost"></i>
                </div>
                
                <!-- Floating Small Elements -->
                <div class="absolute top-4 right-8 text-rose-400 animate-bounce delay-100 text-xl"><i class="fa-solid fa-question"></i></div>
                <div class="absolute bottom-8 left-6 text-amber-400 animate-bounce delay-300 text-2xl"><i class="fa-regular fa-compass"></i></div>
                <div class="absolute top-1/2 -right-4 text-emerald-400 animate-pulse text-lg"><i class="fa-solid fa-magnifying-glass"></i></div>
            </div>
        </div>

        <!-- Text Content -->
        <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-slate-900 dark:text-white mb-4 tracking-tight">
            Oops! Halaman Tidak Ditemukan.
        </h1>
        <p class="text-base sm:text-lg text-slate-500 dark:text-slate-400 max-w-2xl mx-auto mb-10 leading-relaxed">
            Sepertinya Anda tersesat di lorong yang salah. Halaman yang Anda cari mungkin telah dipindahkan, dihapus, atau memang tidak pernah ada.
        </p>

        <!-- Call to Action Buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 w-full sm:w-auto">
            <button onclick="window.history.back()" class="w-full sm:w-auto px-8 py-3.5 rounded-xl font-semibold text-slate-700 bg-white dark:text-slate-300 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm hover:shadow transition-all flex items-center justify-center gap-2 group">
                <i class="fa-solid fa-arrow-left group-hover:-translate-x-1 transition-transform"></i> Kembali
            </button>
            <a href="{{ route('home') }}" wire:navigate class="w-full sm:w-auto px-8 py-3.5 rounded-xl font-bold text-white bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 shadow-lg shadow-brand-500/30 hover:shadow-xl hover:shadow-brand-500/40 hover:-translate-y-0.5 transition-all flex items-center justify-center gap-2 group">
                <i class="fa-solid fa-house group-hover:scale-110 transition-transform"></i> Beranda Utama
            </a>
        </div>

        <!-- Help Link -->
        <div class="mt-12 text-sm text-slate-500 dark:text-slate-400">
            Butuh bantuan? <a href="{{ route('home') }}" class="font-medium text-brand-600 dark:text-brand-400 hover:underline" wire:navigate>Hubungi Administrator</a>
        </div>
    </main>

    <!-- Simplified Footer -->
    <footer class="relative z-10 w-full py-6 text-center text-sm text-slate-500 dark:text-slate-500 border-t border-slate-200/50 dark:border-slate-800/50 backdrop-blur-sm">
        <p>&copy; {{ date('Y') }} Tim IT SMANSA
                    All rights reserved.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Dark Mode Toggle Logic
            const themeToggleBtn = document.getElementById('theme-toggle');
            const themeIcon = document.getElementById('theme-icon');

            const updateThemeIcon = (isDark) => {
                if (themeIcon) {
                    themeIcon.className = isDark ? 'fa-solid fa-sun text-lg' : 'fa-solid fa-moon text-lg';
                }
            };

            updateThemeIcon(document.documentElement.classList.contains('dark'));

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', () => {
                    document.documentElement.classList.toggle('dark');
                    const isDarkMode = document.documentElement.classList.contains('dark');
                    localStorage.setItem('color-theme', isDarkMode ? 'dark' : 'light');
                    updateThemeIcon(isDarkMode);
                });
            }
        });
    </script>
</body>
</html>