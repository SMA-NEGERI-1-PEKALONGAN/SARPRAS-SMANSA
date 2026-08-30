<?php

use Livewire\Component; 
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
new #[Layout('layouts::auth')] class extends Component 
{
    public string $username = '';
    public string $password = '';
    public bool $remember = false;
    // jika sudah login maka alihkan ke halaman dashboard
    public function mount()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
    }
    public function login()
    {
        $credentials = [
            'username' => $this->username,
            'password' => $this->password,
        ];

        // Proses login
        if (Auth::attempt($credentials, $this->remember)) {
            $user = Auth::user();
            
            // Panggil Alpine Toast via Livewire Dispatch
            $this->dispatch('toast', type: 'success', message: 'Login berhasil! Mengalihkan...');
            
            // Regenerate session untuk keamanan
            session()->regenerate();
            
            // jika role admin
            if ($user->role == 'admin') {
                return redirect()->route('admin.dashboard');
            }else{
                return redirect()->route('booking');
            }
        }

        // Jika gagal
        $this->dispatch('toast', type: 'error', message: 'Username atau kata sandi tidak valid!');
    }
};
?>
<main>
    <div class="fixed inset-0 flex overflow-hidden bg-gray-50 dark:bg-gray-950" x-data>

        {{-- Decorative Background Elements --}}
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] bg-indigo-500/10 rounded-full blur-[120px] dark:bg-indigo-500/5"></div>
            <div class="absolute -bottom-[10%] -right-[10%] w-[40%] h-[40%] bg-purple-500/10 rounded-full blur-[120px] dark:bg-purple-500/5"></div>
            <div class="absolute inset-0 opacity-[0.03] dark:opacity-[0.05]" style="background-image: radial-gradient(#4f46e5 1px, transparent 1px); background-size: 24px 24px;"></div>
        </div>

        {{-- Theme Switcher Floating --}}
        <div class="absolute top-6 right-8 z-[60]">
            <button @click="$store.theme.toggle()" class="flex items-center justify-center w-12 h-12 text-gray-500 transition-all bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-800 dark:text-gray-400 hover:text-indigo-600 active:scale-95">
                <i class="text-2xl ti" :class="$store.theme.theme === 'light' ? 'ti-moon' : 'ti-sun'"></i>
            </button>
        </div>

        <div class="relative flex items-center justify-center w-full p-6 mx-auto max-w-7xl md:p-12">
            <div class="w-full max-w-5xl grid md:grid-cols-2 bg-white dark:bg-gray-900 rounded-[3rem] shadow-2xl shadow-indigo-500/10 border border-gray-100 dark:border-gray-800 overflow-hidden">

                {{-- Branding Section (Left) --}}
                <div class="relative flex-col justify-between hidden p-12 overflow-hidden bg-indigo-600 md:flex lg:p-16">
                    <div class="absolute top-0 right-0 w-64 h-64 -mt-32 -mr-32 rounded-full bg-white/10 blur-3xl"></div>
                    <div class="absolute bottom-0 left-0 w-64 h-64 -mb-32 -ml-32 rounded-full bg-indigo-400/20 blur-3xl"></div>

                    <div class="relative z-10 flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 text-indigo-600 bg-white shadow-lg rounded-xl">
                            <i class="text-xs fa-solid fa-school"></i>
                        </div>
                        <span class="text-xl font-bold tracking-tight text-white">SMAN 1 Pekalongan</span>
                    </div>

                    <div class="relative z-10 space-y-6">
                        <h2 class="text-4xl lg:text-5xl font-bold text-white leading-[1.1] tracking-tight">
                            Sistem <br> <span class="text-indigo-200">Peminjaman Ruang</span>
                        </h2>
                        <p class="max-w-sm font-medium leading-relaxed text-indigo-100/80">
                            Kelola jadwal ruangan dengan mudah, cepat, dan terintegrasi.
                        </p>
                    </div>
                </div>

                {{-- Form Section (Right) --}}
                <div class="flex flex-col justify-center p-10 bg-white lg:p-16 dark:bg-gray-900">
                    <div class="space-y-8">
                        <div>
                            <h3 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Login</h3>
                            <p class="mt-2 font-medium text-gray-500 dark:text-gray-400">Selamat datang kembali! Silakan masukkan kredensial Anda.</p>
                        </div>

                        {{-- Form Livewire: Submit akan memanggil method $login di atas --}}
                        <form wire:submit="login" class="space-y-5">
                            <div class="space-y-4">
                                {{-- Pastikan Anda sudah memiliki komponen <x-input> di folder resources/views/components/ --}}
                                <x-input wire:model="username" label="Username" name="username" type="text" icon="user" placeholder="Masukan username" id="username" required />

                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <label class="text-sm font-bold text-gray-700 dark:text-gray-300">Kata Sandi</label>
                                    </div>
                                    <x-input wire:model="password" name="password" type="password" icon="lock" id="password" placeholder="••••••••" required />
                                </div>
                            </div>

                            <div class="flex items-center">
                                <x-checkbox wire:model="remember" name="remember" label="Biarkan saya tetap masuk" />
                            </div>

                            {{-- Tombol Login dengan efek loading bawaan Livewire --}}
                            <x-button type="submit" variant="primary" class="w-full py-4 shadow-lg rounded-2xl shadow-indigo-600/20">
                                <span wire:loading.remove>Masuk</span>
                                <span wire:loading><i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Memuat...</span>
                            </x-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Toast Component Anda --}}
    <x-toast />
</main>