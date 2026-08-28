@php
    // Dummy Data untuk simulasi auth() user
    $user = (object) [
        'name' => 'Rafael Moreno',
        'email' => 'rafael.moreno@adminpro.com',
        'avatar' => null, // Isi path string jika ingin mencoba gambar
        'initials' => 'RM',
        'role' => 'Super Administrator',
        'employee_code' => 'EMP-2024-001'
    ];
@endphp

<div x-show="userOffcanvasOpen" class="fixed inset-0 z-[100] overflow-hidden" x-cloak>
    {{-- Backdrop dengan Blur --}}
    <div x-show="userOffcanvasOpen" 
        x-transition:enter="transition-opacity ease-out duration-300"
        x-transition:enter-start="opacity-0" 
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-200" 
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" 
        @click="userOffcanvasOpen = false"
        class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm"></div>

    <div class="fixed inset-y-0 right-0 flex max-w-full pl-10">
        <div x-show="userOffcanvasOpen" 
            x-transition:enter="transform transition ease-out duration-300"
            x-transition:enter-start="translate-x-full" 
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in duration-200" 
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full" 
            class="w-screen max-w-sm">

            <div class="flex h-full flex-col bg-white dark:bg-gray-950 border-l border-gray-100 dark:border-gray-900 shadow-2xl">
                
                {{-- User Profile Header --}}
                <div class="px-6 pt-8 pb-6 shrink-0 border-b border-gray-100 dark:border-gray-900">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4 min-w-0">
                            {{-- Avatar Container --}}
                            <div class="relative shrink-0">
                                <div class="h-16 w-16 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 p-0.5 border border-indigo-100 dark:border-indigo-500/20">
                                    <div class="h-full w-full rounded-xl bg-white dark:bg-gray-900 flex items-center justify-center overflow-hidden shadow-inner">
                                        @if ($user->avatar)
                                            <img src="{{ asset('storage/' . $user->avatar) }}" class="w-full h-full object-cover">
                                        @else
                                            <span class="font-black text-xl text-indigo-600 dark:text-indigo-400">
                                                {{ $user->initials }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                {{-- Status Indicator --}}
                                <span class="absolute bottom-0 right-0 h-3.5 w-3.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-gray-950"></span>
                            </div>

                            {{-- Name & Email --}}
                            <div class="min-w-0 leading-tight">
                                <h2 class="text-sm font-bold text-gray-900 dark:text-white truncate capitalize">
                                    {{ $user->name }}
                                </h2>
                                <p class="text-[11px] font-medium text-gray-400 dark:text-gray-500 truncate mt-0.5">
                                    {{ $user->email }}
                                </p>
                            </div>
                        </div>

                        {{-- Close Button --}}
                        <button @click="userOffcanvasOpen = false"
                            class="p-2 rounded-xl text-gray-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 transition-all active:scale-90">
                            <i class="ti ti-x text-xl"></i>
                        </button>
                    </div>

                    {{-- Badges --}}
                    <div class="mt-5 flex flex-wrap gap-2">
                        <span class="px-3 py-1 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-[10px] font-black uppercase tracking-wider rounded-lg border border-indigo-100 dark:border-indigo-500/20">
                            {{ $user->role }}
                        </span>
                        <span class="px-3 py-1 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-[10px] font-black uppercase tracking-wider rounded-lg border border-gray-100 dark:border-gray-800">
                            ID: {{ $user->employee_code }}
                        </span>
                    </div>
                </div>

                {{-- Menu Links --}}
                <div class="flex-1 overflow-y-auto px-4 py-6 space-y-1 custom-scrollbar">
                    <p class="px-4 text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-3">
                        Pengaturan Akun
                    </p>

                    <a href="#" class="flex items-center gap-3 p-3 rounded-2xl text-gray-600 dark:text-gray-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all group">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-50 dark:bg-gray-900 group-hover:bg-white dark:group-hover:bg-gray-800 transition-colors border border-transparent group-hover:border-indigo-100 dark:group-hover:border-indigo-900/50">
                            <i class="ti ti-user-circle text-xl transition-transform group-hover:scale-110"></i>
                        </div>
                        <span class="text-xs font-bold">Informasi Profil</span>
                    </a>

                    <a href="#" class="flex items-center gap-3 p-3 rounded-2xl text-gray-600 dark:text-gray-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all group">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-50 dark:bg-gray-900 group-hover:bg-white dark:group-hover:bg-gray-800 transition-colors border border-transparent group-hover:border-indigo-100 dark:group-hover:border-indigo-900/50">
                            <i class="ti ti-lock-square-rounded text-xl transition-transform group-hover:scale-110"></i>
                        </div>
                        <span class="text-xs font-bold">Keamanan & Password</span>
                    </a>

                    <div class="pt-8">
                        <p class="px-4 text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-3">
                            Personalisasi
                        </p>

                        {{-- Dark Mode Toggle --}}
                        <div class="flex items-center justify-between p-3 rounded-2xl hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-50 dark:bg-gray-900">
                                    <i class="ti ti-moon-stars text-xl text-gray-600 dark:text-gray-400"></i>
                                </div>
                                <span class="text-xs font-bold text-gray-600 dark:text-gray-400">
                                    Tema Mode Gelap
                                </span>
                            </div>

                            <button @click="setTheme(theme === 'dark' ? 'light' : 'dark')"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-300 ease-in-out focus:outline-none"
                                :class="theme === 'dark' ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'">
                                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-lg transition duration-300 ease-in-out"
                                    :class="theme === 'dark' ? 'translate-x-5' : 'translate-x-0'">
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Footer: Logout --}}
                <div class="p-6 bg-gray-50/30 dark:bg-gray-900/40 border-t border-gray-100 dark:border-gray-900">
                    <form method="POST" action="#">
                        @csrf
                        <button type="button"
                            class="w-full flex items-center justify-center gap-3 py-3.5 bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 text-sm font-black rounded-2xl hover:bg-rose-600 hover:text-white dark:hover:bg-rose-600 dark:hover:text-white transition-all shadow-sm active:scale-[0.98] group">
                            <i class="ti ti-logout-2 text-xl transition-transform group-hover:translate-x-1"></i>
                            Keluar dari Sesi
                        </button>
                    </form>
                    <p class="text-center text-[10px] text-gray-400 mt-4 font-bold uppercase tracking-widest">
                        Versi Aplikasi 2.4.0
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>