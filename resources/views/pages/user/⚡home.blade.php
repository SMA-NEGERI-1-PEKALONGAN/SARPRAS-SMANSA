<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Room;

new
    #[Layout('layouts.user')]
    #[Title('Beranda')]
    class extends Component
{
    public function goToBooking()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        return redirect()->route('booking');
    }

    // public function render()
    // {
    //     return view('livewire.user.home');
    // }

    public function with(): array
    {
        return [
            'rooms' => Room::query()
                ->where('status_tersedia', true)
                ->orderBy('nama_ruangan')
                ->get(),

            'totalRooms' => Room::count(),

            'availableRooms' => Room::where('status_tersedia', true)->count(),
        ];
    }

};

?>

<div>
    
    {{-- ========================================================= --}}
    {{-- HERO --}}
    {{-- ========================================================= --}}
    <section id="hero" class="relative overflow-hidden pt-24 pb-20 sm:pt-28 lg:pt-32 lg:pb-32 transition-colors duration-300" >

        {{-- Background --}}
        <div class="absolute top-1/4 -left-20
                   w-72 h-72
                   bg-blue-500/10 dark:bg-blue-500/20
                   rounded-full blur-3xl
                   pointer-events-none"></div>

        <div class="absolute top-1/3 -right-20
                   w-80 h-80
                   bg-indigo-500/10 dark:bg-indigo-500/20
                   rounded-full blur-3xl
                   pointer-events-none"></div>

        <div class="relative mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8"> 
            <div class="grid w-full min-w-0 items-center gap-10 sm:gap-12 lg:grid-cols-12 lg:gap-8">

                {{-- ================================================= --}}
                {{-- HERO CONTENT --}}
                {{-- ================================================= --}}
                <div class=" w-full min-w-0 space-y-6 text-center lg:col-span-7 lg:text-left">

                    <div
                        class="mx-auto inline-flex max-w-full items-center gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-300 sm:px-4 sm:text-sm lg:mx-0">
                        <span class="flex w-2 h-2
                                   rounded-full
                                   bg-blue-600 dark:bg-blue-400
                                   animate-pulse"></span>
                        Sistem Peminjaman Ruang Instan & Transparan
                    </div>

                    <h1 class="text-[2.25rem] leading-[1.1] font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-5xl lg:text-6xl">
                        Pinjam Ruangan
                        <span class="bg-gradient-to-r
                                   from-blue-600 to-indigo-600
                                   dark:from-blue-400 dark:to-indigo-400
                                   bg-clip-text text-transparent">
                            Tanpa Ribet.
                        </span>
                    </h1>

                    <p class="max-w-2xl mx-auto
                               text-lg leading-relaxed
                               text-slate-600 dark:text-slate-400
                               lg:mx-0">
                        Cek ketersediaan laboratorium, aula,
                        ruang kelas, dan fasilitas lainnya secara
                        real-time. Ajukan peminjaman dengan cepat,
                        mudah, dan transparan.
                    </p>

                    {{-- CTA --}}
                    <div class="flex flex-col items-center
                               justify-center gap-4 pt-2
                               sm:flex-row
                               lg:justify-start">

                        <button type="button" wire:click="goToBooking" class="w-full sm:w-auto
                                   px-8 py-4 rounded-xl
                                   font-semibold text-white
                                   bg-gradient-to-r
                                   from-blue-600 to-indigo-600
                                   hover:from-blue-700
                                   hover:to-indigo-700
                                   shadow-xl shadow-blue-500/25
                                   hover:-translate-y-0.5
                                   transition-all
                                   flex items-center
                                   justify-center gap-3">
                            <i class="text-lg fa-solid fa-calendar-check"></i>
                            <span>Mulai Peminjaman</span>
                        </button>

                        <a href="#alur" class="flex items-center
                                   justify-center gap-2
                                   w-full sm:w-auto
                                   px-8 py-4 rounded-xl
                                   font-semibold
                                   text-slate-700 dark:text-slate-200
                                   bg-white dark:bg-slate-800
                                   border border-slate-200 dark:border-slate-700
                                   hover:bg-slate-50
                                   dark:hover:bg-slate-700
                                   transition-all">
                            <i class="text-blue-600 dark:text-blue-400 fa-solid fa-circle-play"></i>
                            Pelajari Alur
                        </a>
                    </div>

                    {{-- Statistics --}}
                    {{-- FIX: Mengecilkan gap di mobile (gap-2) agar isi tidak meluap ke samping --}}
                    <div class="grid grid-cols-3 gap-2 sm:gap-4
                            pt-8
                            text-center lg:text-left
                            border-t
                            border-slate-200/80
                            dark:border-slate-700/80">

                        <div>
                            <p class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">
                                {{ $totalRooms }}
                            </p>
                            {{-- FIX: Mengecilkan ukuran teks label (text-[10px]) di mobile --}}
                            <p class="text-[10px] sm:text-sm font-medium text-slate-500 dark:text-slate-400">
                                Total Ruangan
                            </p>
                        </div>

                        <div>
                            <p class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">
                                {{ $availableRooms }}
                            </p>
                            <p class="text-[10px] sm:text-sm font-medium text-slate-500 dark:text-slate-400">
                                Bisa Dipinjam
                            </p>
                        </div>

                        <div>
                            <p class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">
                                24/7
                            </p>
                            <p class="text-[10px] sm:text-sm font-medium text-slate-500 dark:text-slate-400">
                                Akses Sistem
                            </p>
                        </div>
                    </div>

                </div>

                {{-- ================================================= --}}
                {{-- PREVIEW CARD --}}
                {{-- ================================================= --}}
                <div class="relative min-w-0 lg:col-span-5"> <div class="relative mx-auto w-full max-w-md lg:max-w-none">

                        {{-- ================================================= --}}
                        {{-- MAIN CARD --}}
                        {{-- ================================================= --}}
                        <div class="relative p-6
                                bg-white dark:bg-slate-800
                                rounded-3xl
                                shadow-2xl
                                shadow-slate-200/80
                                dark:shadow-black/50
                                border
                                border-slate-100
                                dark:border-slate-700">

                            {{-- CARD HEADER --}}
                            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 bg-rose-500 rounded-full"></span>
                                    <span class="w-3 h-3 bg-amber-500 rounded-full"></span>
                                    <span class="w-3 h-3 bg-emerald-500 rounded-full"></span>
                                </div>

                                <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20">
                                    <i class="mr-1 text-[8px] fa-solid fa-circle"></i>
                                    {{ $availableRooms }} Ruangan Aktif
                                </span>
                            </div>

                            {{-- ROOM LIST --}}
                            <div x-data="roomScroller()" x-init="init()" class="relative mt-5">
                                <div class="absolute top-0 left-0 right-0 z-10 h-8 bg-gradient-to-b from-white dark:from-slate-800 to-transparent pointer-events-none"></div>

                                <div x-ref="roomContainer" @mouseenter="pause()" @mouseleave="resume()" @touchstart="pause()" @touchend="resumeDelayed()" @wheel="pause()" class="space-y-4 room-scroll max-h-[390px] overflow-y-auto pr-1 scroll-smooth overscroll-contain scrollbar-thin scrollbar-thumb-slate-300 dark:scrollbar-thumb-slate-600" style="scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent;">
                                    
                                    @forelse($rooms as $room)
                                        @php
                                            $icon = trim($room->icon ?: 'fa-solid fa-door-open');
                                            if (str_starts_with($icon, 'fa-') && !str_contains($icon, 'fa-solid') && !str_contains($icon, 'fa-regular') && !str_contains($icon, 'fa-brands')) {
                                                $icon = 'fa-solid ' . $icon;
                                            }
                                        @endphp
                                        <div class="group flex items-center justify-between room-scroll gap-3 p-4 border rounded-2xl bg-slate-50 dark:bg-slate-700/50 border-slate-100 dark:border-slate-600 hover:border-blue-300 dark:hover:border-blue-500/50 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300">
                                            <div class="flex items-center min-w-0 gap-3.5">
                                                <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400">
                                                    <i class="text-lg {{ $icon }}"></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <h4 class="text-sm font-semibold truncate text-slate-800 dark:text-slate-100">
                                                        {{ $room->nama_ruangan }}
                                                    </h4>
                                                    <p class="flex items-center gap-1 mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                                        <i class="fa-solid fa-users"></i>
                                                        {{ $room->kapasitas }} Orang
                                                    </p>
                                                    @if($room->lokasi)
                                                    <p class="flex items-center gap-1 mt-0.5 text-[10px] text-slate-400 dark:text-slate-500">
                                                        <i class="fa-solid fa-location-dot"></i>
                                                        {{ $room->lokasi }}
                                                    </p>
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="flex-shrink-0 px-3 py-1 rounded-full text-[10px] sm:text-xs font-semibold bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300">
                                                <i class="mr-1 fa-solid fa-check"></i> Aktif
                                            </span>
                                        </div>
                                    @empty
                                        <div class="py-12 text-center">
                                            <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 rounded-2xl bg-slate-100 dark:bg-slate-700">
                                                <i class="text-xl fa-solid fa-door-closed text-slate-400"></i>
                                            </div>
                                            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">
                                                Belum ada ruangan tersedia
                                            </p>
                                        </div>
                                    @endforelse
                                </div>
                                <div class="absolute bottom-0 left-0 right-0 z-10 h-10 bg-gradient-to-t from-white dark:from-slate-800 to-transparent pointer-events-none"></div>
                            </div>
                        </div>

                        {{-- ================================================= --}}
                        {{-- FLOATING STATUS - DI LUAR CARD --}}
                        {{-- ================================================= --}}
                        {{-- FIX: Ubah left-0 menjadi center murni pada mobile menggunakan translate-x --}}
                        <div class="absolute
                                left-1/2 -translate-x-1/2 sm:-left-5 sm:translate-x-0
                                -bottom-8
                                z-20
                                flex items-center gap-3
                                p-4
                                w-[85%] sm:w-auto sm:min-w-[250px]
                                glass-card
                                rounded-2xl
                                shadow-xl
                                animate-float">

                            <div class="flex items-center justify-center
                                    flex-shrink-0
                                    w-10 h-10
                                    text-lg font-bold
                                    text-white
                                    bg-emerald-500
                                    rounded-full
                                    shadow-lg
                                    shadow-emerald-500/30">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>

                            <div class="min-w-0">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-300">
                                    Ruang Aktif
                                </p>
                                <p class="text-sm font-bold text-slate-800 dark:text-white whitespace-nowrap">
                                    {{ $availableRooms }} Ruangan Siap Dipinjam
                                </p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>


    {{-- ========================================================= --}}
    {{-- ALUR PEMINJAMAN --}}
    {{-- ========================================================= --}}
    <section id="alur" class="relative py-20
               bg-white dark:bg-slate-900
               transition-colors duration-300">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="max-w-3xl mx-auto mb-16
                       space-y-3 text-center">

                <span class="text-sm font-semibold tracking-wider
                           text-blue-600 dark:text-blue-400 uppercase">
                    Proses Mudah & Cepat
                </span>

                <h2 class="text-3xl sm:text-4xl
                           font-extrabold
                           text-slate-900 dark:text-white">
                    Alur & Tata Cara Peminjaman
                </h2>

                <p class="text-slate-600 dark:text-slate-400">
                    Ikuti empat langkah sederhana untuk mendapatkan
                    izin penggunaan ruangan.
                </p>

            </div>

            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4">

                @php
                $steps = [
                [
                'number' => '1',
                'color' => 'blue',
                'title' => 'Cari & Pilih Ruang',
                'text' => 'Pilih tanggal dan ruangan yang sesuai kebutuhan kegiatan Anda.',
                'footer' => 'Lihat Jadwal Realtime',
                ],
                [
                'number' => '2',
                'color' => 'indigo',
                'title' => 'Isi Form Peminjaman',
                'text' => 'Lengkapi informasi kegiatan, waktu, jumlah peserta, dan kebutuhan fasilitas.',
                'footer' => 'Formulir Digital Ringkas',
                ],
                [
                'number' => '3',
                'color' => 'amber',
                'title' => 'Verifikasi Petugas',
                'text' => 'Pengajuan diperiksa oleh petugas sarpras dan status dapat dipantau.',
                'footer' => 'Persetujuan Cepat',
                ],
                [
                'number' => '4',
                'color' => 'emerald',
                'title' => 'Gunakan Ruangan',
                'text' => 'Gunakan bukti peminjaman digital sebagai tanda bahwa ruangan telah disetujui.',
                'footer' => 'E-Permit Digital',
                ],
                ];
                @endphp

                @foreach($steps as $step)

                <div class="relative p-6
                               bg-slate-50
                               dark:bg-slate-800/80
                               rounded-2xl
                               border
                               border-slate-100
                               dark:border-slate-700
                               hover:shadow-xl
                               hover:-translate-y-1
                               transition-all">

                    <div class="flex items-center justify-center
                                   w-14 h-14 mb-6
                                   text-xl font-bold text-white
                                   rounded-2xl
                                   bg-{{ $step['color'] }}-600">
                        {{ $step['number'] }}
                    </div>

                    <h3 class="mb-2 text-lg font-bold
                                   text-slate-800 dark:text-white">
                        {{ $step['title'] }}
                    </h3>

                    <p class="text-sm leading-relaxed
                                   text-slate-600 dark:text-slate-400">
                        {{ $step['text'] }}
                    </p>

                    <div class="flex items-center gap-1
                                   pt-4 mt-4
                                   border-t
                                   border-slate-200/60
                                   dark:border-slate-700/60
                                   text-xs font-semibold
                                   text-{{ $step['color'] }}-600
                                   dark:text-{{ $step['color'] }}-400">
                        <span>
                            {{ $step['footer'] }}
                        </span>

                        <i class="text-[10px] fa-solid fa-chevron-right"></i>
                    </div>

                </div>

                @endforeach

            </div>

        </div>

    </section>

    
    {{-- ========================================================= --}}
    {{-- KEUNGGULAN --}}
    {{-- ========================================================= --}}
    <section id="fitur" class="relative py-20 overflow-hidden
               text-white
               bg-slate-900 dark:bg-slate-950">

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="max-w-3xl mx-auto mb-16
                       space-y-3 text-center">

                <span class="text-sm font-semibold tracking-wider
                           text-blue-400 uppercase">
                    Mengapa RuangKu?
                </span>

                <h2 class="text-3xl sm:text-4xl font-extrabold">
                    Keunggulan Layanan Peminjaman
                </h2>

                <p class="text-slate-400">
                    Pengelolaan ruangan menjadi lebih mudah,
                    transparan, dan efisien.
                </p>

            </div>

            <div class="grid gap-8 md:grid-cols-3">

                @php
                $features = [
                [
                'icon' => 'fa-clock-rotate-left',
                'color' => 'blue',
                'title' => 'Jadwal Real-time',
                'text' => 'Lihat ketersediaan ruangan dan waktu peminjaman sebelum mengajukan booking.',
                ],
                [
                'icon' => 'fa-shield-halved',
                'color' => 'indigo',
                'title' => 'Akses Terverifikasi',
                'text' => 'Setiap peminjaman menggunakan akun yang sudah terdaftar dan terotorisasi.',
                ],
                [
                'icon' => 'fa-mobile-screen-button',
                'color' => 'emerald',
                'title' => 'Responsif',
                'text' => 'Sistem dapat digunakan dengan nyaman melalui Smartphone, Tablet, maupun Desktop.',
                ],
                ];
                @endphp

                @foreach($features as $feature)

                <div class="p-8 rounded-2xl
                               bg-slate-800/80
                               border border-slate-700/60
                               hover:border-{{ $feature['color'] }}-500/50
                               transition-all">

                    <div class="flex items-center justify-center
                                   w-12 h-12 mb-6
                                   text-2xl
                                   rounded-xl
                                   bg-{{ $feature['color'] }}-500/10
                                   text-{{ $feature['color'] }}-400">
                        <i class="fa-solid {{ $feature['icon'] }}"></i>
                    </div>

                    <h3 class="mb-3 text-xl font-bold">
                        {{ $feature['title'] }}
                    </h3>

                    <p class="text-sm leading-relaxed text-slate-400">
                        {{ $feature['text'] }}
                    </p>

                </div>

                @endforeach

            </div>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- FAQ --}}
    {{-- ========================================================= --}}
    <section id="faq" class="py-20
               bg-slate-50 dark:bg-slate-900
               transition-colors duration-300" x-data="{ activeFaq: null }">

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-14 space-y-3 text-center">

                <span class="text-sm font-semibold tracking-wider
                           text-blue-600 dark:text-blue-400 uppercase">
                    Pertanyaan Umum
                </span>
                
                <h2 class="text-3xl font-extrabold
                           text-slate-900 dark:text-white">
                    Sering Ditanyakan
                </h2>

                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Berikut jawaban untuk beberapa pertanyaan
                    yang sering diajukan.
                </p>

            </div>

            <div class="space-y-4">

                @php
                $faqs = [
                [
                'question' => 'Siapa saja yang diperbolehkan meminjam ruangan?',
                'answer' => 'Pengguna yang telah memiliki akun dan mendapatkan hak akses peminjaman dapat mengajukan
                penggunaan ruangan sesuai ketentuan yang berlaku.',
                ],
                [
                'question' => 'Bagaimana cara mengetahui ruangan masih tersedia?',
                'answer' => 'Ketersediaan ruangan dapat dilihat melalui jadwal peminjaman sebelum pengguna mengajukan
                booking.',
                ],
                [
                'question' => 'Bagaimana jika pengajuan saya ditolak?',
                'answer' => 'Status pengajuan dapat dipantau melalui menu Riwayat Peminjaman. Apabila ditolak, pengguna
                dapat melihat informasi status dan mengajukan kembali dengan jadwal yang sesuai.',
                ],
                ];
                @endphp

                @foreach($faqs as $index => $faq)

                <div class="overflow-hidden
                               bg-white dark:bg-slate-800
                               rounded-2xl
                               border
                               border-slate-200 dark:border-slate-700">

                    <button type="button" @click="activeFaq = activeFaq === {{ $index }} ? null : {{ $index }}" class="flex items-center
                                   justify-between gap-4
                                   w-full px-6 py-5
                                   font-semibold text-left
                                   text-slate-800 dark:text-white">

                        <span>
                            {{ $faq['question'] }}
                        </span>

                        <i class="transition-transform duration-300
                                       fa-solid fa-chevron-down" :class="activeFaq === {{ $index }}
                                    ? 'rotate-180'
                                    : ''"></i>

                    </button>

                    <div x-show="activeFaq === {{ $index }}" x-collapse x-cloak class="px-6 pb-5 pt-3
                                   text-sm leading-relaxed
                                   border-t
                                   border-slate-100
                                   dark:border-slate-700
                                   text-slate-600
                                   dark:text-slate-400">
                        {{ $faq['answer'] }}
                    </div>

                </div>

                @endforeach

            </div>

        </div>

    </section>

</div>
@script
<script>
    Alpine.data('roomScroller', () => ({
        interval: null,
        isPaused: false,
        isAtBottom: false,

        init() {
            const container = this.$refs.roomContainer;

            if (!container) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Tidak perlu auto-scroll jika konten tidak melebihi container
            |--------------------------------------------------------------------------
            */
            if (container.scrollHeight <= container.clientHeight) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Pause saat mouse berada di area scroll
            |--------------------------------------------------------------------------
            */
            container.addEventListener('mouseenter', () => {
                this.isPaused = true;
            });

            /*
            |--------------------------------------------------------------------------
            | Lanjut kembali saat mouse keluar
            |--------------------------------------------------------------------------
            | Jika sudah mencapai bawah, tidak akan dilanjutkan.
            |--------------------------------------------------------------------------
            */
            container.addEventListener('mouseleave', () => {
                if (!this.isAtBottom) {
                    this.isPaused = false;
                }
            });

            /*
            |--------------------------------------------------------------------------
            | Pause saat touch
            |--------------------------------------------------------------------------
            */
            container.addEventListener(
                'touchstart',
                () => {
                    this.isPaused = true;
                }, {
                    passive: true
                }
            );

            /*
            |--------------------------------------------------------------------------
            | Setelah touch selesai
            |--------------------------------------------------------------------------
            */
            container.addEventListener(
                'touchend',
                () => {
                    setTimeout(() => {
                        if (!this.isAtBottom) {
                            this.isPaused = false;
                        }
                    }, 1500);
                }, {
                    passive: true
                }
            );

            /*
            |--------------------------------------------------------------------------
            | Cek ketika user melakukan scroll manual
            |--------------------------------------------------------------------------
            */
            container.addEventListener(
                'scroll',
                () => {
                    const maxScroll =
                        container.scrollHeight -
                        container.clientHeight;

                    /*
                    | Jika sudah mencapai paling bawah,
                    | hentikan auto-scroll.
                    */
                    if (
                        maxScroll <= 0 ||
                        container.scrollTop >= maxScroll - 2
                    ) {
                        this.isAtBottom = true;
                        this.isPaused = true;

                        this.stopAutoScroll();
                    }
                }, {
                    passive: true
                }
            );

            /*
            |--------------------------------------------------------------------------
            | Auto Scroll
            |--------------------------------------------------------------------------
            */
            this.interval = setInterval(() => {

                if (this.isPaused || this.isAtBottom) {
                    return;
                }

                const maxScroll =
                    container.scrollHeight -
                    container.clientHeight;

                /*
                |--------------------------------------------------------------------------
                | Jika sudah di bawah
                |--------------------------------------------------------------------------
                */
                if (container.scrollTop >= maxScroll - 2) {

                    container.scrollTop = maxScroll;

                    this.isAtBottom = true;
                    this.isPaused = true;

                    this.stopAutoScroll();

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Scroll perlahan ke bawah
                |--------------------------------------------------------------------------
                */
                container.scrollTop += 0.5;

            }, 40);
        },

        stopAutoScroll() {
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
        },

        destroy() {
            this.stopAutoScroll();
        }
    }));

</script>
@endscript
