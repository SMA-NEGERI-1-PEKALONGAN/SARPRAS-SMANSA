<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

new #[Layout('layouts.user')] #[Title('Manajemen Profil Akun')] class extends Component
{
    public string $name = '';
    public string $username = '';
    public string $catatan = '';
    public string $no_hp = '';

    public string $passwordLama = '';
    public string $passwordBaru = '';
    public string $passwordConfirmation = '';

    public function mount(): void
    {
        $user = auth()->user();

        abort_unless($user, 403);

        $this->name = (string) ($user->name ?? '');
        $this->username = (string) ($user->username ?? '');
        $this->catatan = (string) ($user->catatan ?? '');
        $this->no_hp = (string) ($user->no_hp ?? $user->no_wa ?? '');
    }

    public function saveProfile(): void
    {
        $this->validate([
            'no_hp' => ['required', 'string', 'max:30'],
        ], [
            'no_hp.required' => 'No. HP wajib diisi.',
            'no_hp.max' => 'No. HP maksimal 30 karakter.',
        ]);

        try {
            $user = auth()->user();
            $user->no_hp = trim($this->no_hp);
            $user->save();

            $this->dispatch('toast', type: 'success', message: 'Profil berhasil diperbarui.');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('no_hp', 'No. HP gagal diperbarui. Silakan coba lagi.');
        }
    }

    public function updatedPasswordBaru(): void
    {
        $this->resetValidation('passwordConfirmation');

        if ($this->passwordConfirmation !== '' && $this->passwordBaru !== $this->passwordConfirmation) {
            $this->addError('passwordConfirmation', 'Password baru dan konfirmasi tidak cocok.');
        }
    }

    public function updatedPasswordConfirmation(): void
    {
        $this->resetValidation('passwordConfirmation');

        if ($this->passwordConfirmation !== '' && $this->passwordBaru !== $this->passwordConfirmation) {
            $this->addError('passwordConfirmation', 'Password baru dan konfirmasi tidak cocok.');
        }
    }

    public function updatePassword(): void
    {
        $this->validate([
            'passwordLama' => ['required'],
            'passwordBaru' => ['required', 'different:passwordLama', Password::min(8)],
            'passwordConfirmation' => ['required', 'same:passwordBaru'],
        ], [
            'passwordLama.required' => 'Password lama wajib diisi.',
            'passwordBaru.required' => 'Password baru wajib diisi.',
            'passwordBaru.different' => 'Password baru harus berbeda dari password lama.',
            'passwordConfirmation.required' => 'Konfirmasi password wajib diisi.',
            'passwordConfirmation.same' => 'Password baru dan konfirmasi tidak cocok.',
        ]);

        $user = auth()->user();

        if (!Hash::check($this->passwordLama, (string) $user->password)) {
            $this->addError('passwordLama', 'Password lama tidak benar.');
            return;
        }

        try {
            $user->password = Hash::make($this->passwordBaru);
            $user->save();

            $this->reset(['passwordLama', 'passwordBaru', 'passwordConfirmation']);
            $this->resetValidation();
            $this->dispatch('toast', type: 'success', message: 'Password berhasil diperbarui.');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('passwordBaru', 'Password gagal diperbarui. Silakan coba lagi.');
        }
    }
};
?>

<div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 mt-8">
    <style>
        .profile-scrollbar-hidden::-webkit-scrollbar { display: none; }
        .profile-scrollbar-hidden { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
    {{-- Header --}}
    <div class="my-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white mb-2">
            Manajemen Profil Akun
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Kelola informasi akun dan keamanan password Anda.
        </p>
    </div>

    {{-- Informasi Akun --}}
    <section class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm mb-6 overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 flex items-center justify-center">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">Informasi Akun</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Informasi dasar akun Anda.</p>
                </div>
            </div>
        </div>

        <form wire:submit="saveProfile" class="p-5 sm:p-6 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Nama --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Nama</label>
                    <input type="text" value="{{ $name }}" disabled
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-900/60 px-4 py-3 text-sm text-slate-600 dark:text-slate-300 cursor-not-allowed">
                </div>

                {{-- Catatan --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Catatan</label>
                    <input type="text" value="{{ $catatan ?: '-' }}" disabled
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-900/60 px-4 py-3 text-sm text-slate-600 dark:text-slate-300 cursor-not-allowed">
                </div>

                {{-- Username --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Username</label>
                    <input type="text" value="{{ $username }}" disabled
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-900/60 px-4 py-3 text-sm text-slate-600 dark:text-slate-300 cursor-not-allowed">
                </div>

                {{-- No HP --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        No. HP <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <i class="fa-solid fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" wire:model="no_hp" inputmode="tel" autocomplete="tel"
                            placeholder="Contoh: 081234567890" maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/(\..*)\./g, '$1');"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/50 pl-11 pr-4 py-3 text-sm text-slate-800 dark:text-white placeholder-slate-400 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 transition-all">
                    </div>
                    @error('no_hp')
                        <span class="block mt-1.5 text-xs text-rose-500">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="saveProfile"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 hover:bg-brand-700 disabled:opacity-60 disabled:cursor-not-allowed text-white px-5 py-3 text-sm font-semibold shadow-sm transition-colors">
                    <span wire:loading.remove wire:target="saveProfile">
                        <i class="fa-solid fa-floppy-disk mr-1"></i> Simpan Profil
                    </span>
                    <span wire:loading wire:target="saveProfile">
                        <i class="fa-solid fa-circle-notch animate-spin mr-1"></i> Sedang menyimpan data...
                    </span>
                </button>
            </div>
        </form>
    </section>

    {{-- Perubahan Password --}}
    <section class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">Perubahan Password</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Gunakan password yang kuat dan mudah Anda ingat.</p>
                </div>
            </div>
        </div>

        <form wire:submit="updatePassword" class="p-5 sm:p-6 space-y-5" x-data="{ showOld:false, showNew:false, showConfirm:false }">
            {{-- Password Lama --}}
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Password Lama <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <input :type="showOld ? 'text' : 'password'" wire:model="passwordLama" autocomplete="current-password" placeholder="Password lama"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/50 pl-4 pr-11 py-3 text-sm text-slate-800 dark:text-white outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 transition-all">
                    <button type="button" @click="showOld = !showOld"
                        class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                        <i :class="showOld ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'"></i>
                    </button>
                </div>
                @error('passwordLama')
                    <span class="block mt-1.5 text-xs text-rose-500">{{ $message }}</span>
                @enderror
            </div>

            {{-- Password Baru --}}
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Password Baru <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <input :type="showNew ? 'text' : 'password'" wire:model.live="passwordBaru" autocomplete="new-password" placeholder="Password baru"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/50 pl-4 pr-11 py-3 text-sm text-slate-800 dark:text-white outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 transition-all">
                    <button type="button" @click="showNew = !showNew"
                        class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                        <i :class="showNew ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'"></i>
                    </button>
                </div>
                <p class="mt-1.5 text-[11px] text-slate-400">Minimal 8 karakter.</p>
                @error('passwordBaru')
                    <span class="block mt-1.5 text-xs text-rose-500">{{ $message }}</span>
                @enderror
            </div>

            {{-- Konfirmasi Password --}}
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Konfirmasi Password Baru <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <input :type="showConfirm ? 'text' : 'password'" wire:model.live="passwordConfirmation" autocomplete="new-password" placeholder="Konfirmasi password baru"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/50 pl-4 pr-11 py-3 text-sm text-slate-800 dark:text-white outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 transition-all">
                    <button type="button" @click="showConfirm = !showConfirm"
                        class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                        <i :class="showConfirm ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'"></i>
                    </button>
                </div>
                @if($passwordConfirmation !== '' && $passwordBaru === $passwordConfirmation)
                    <span class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        <i class="fa-solid fa-circle-check"></i> Password cocok.
                    </span>
                @endif
                @error('passwordConfirmation')
                    <span class="block mt-1.5 text-xs text-rose-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="updatePassword"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 disabled:opacity-60 disabled:cursor-not-allowed text-white px-5 py-3 text-sm font-semibold shadow-sm transition-colors">
                    <span wire:loading.remove wire:target="updatePassword">
                        <i class="fa-solid fa-key mr-1"></i> Perbarui Password
                    </span>
                    <span wire:loading wire:target="updatePassword">
                        <i class="fa-solid fa-circle-notch animate-spin mr-1"></i> Sedang menyimpan data...
                    </span>
                </button>
            </div>
        </form>
    </section>

    <x-toast />
</div>