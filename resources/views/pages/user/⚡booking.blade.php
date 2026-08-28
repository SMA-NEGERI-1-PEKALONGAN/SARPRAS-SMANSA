<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Room;
use App\Models\Borrowing;
use App\Models\BorrowingDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.user')] #[Title('Eksplorasi Ruangan')] class extends Component 
{
    // Filter State
    public $dateFilter;
    public $search = '';
    public $category = 'all';

    // Modal & Form State
    public $isBookingModalOpen = false;
    public $isAlertModalOpen = false;
    
    public $selectedRoom = [];
    
    public $form = [
        'waktu_mulai' => '',
        'waktu_selesai' => '',
        'tujuan' => '',
        'catatan' => ''
    ];

    public function mount()
    {
        $this->dateFilter = now()->format('Y-m-d');
    }

    // Reset pagination / state ketika filter berubah
    public function updatingSearch() { }
    public function updatingCategory() { }

    public function getFilteredRoomsProperty()
    {
        // CATATAN: Idealnya ini menggunakan query dari database seperti Room::query()->...[cite: 1]
        // Untuk mempertahankan desain presisi sesuai HTML yang Anda lampirkan, berikut adalah
        // simulasi data dinamis yang sudah dipetakan berdasarkan desain Anda.
        
        $rooms = collect([
            [
                'id' => 1, 'kategori' => 'kelas', 'kategori_label' => 'Ruang Kelas', 
                'nama' => 'Kelas Teori 2.01', 'deskripsi' => 'Ruang kelas ber-AC dengan proyektor, cocok untuk kegiatan belajar mengajar reguler.',
                'kapasitas' => 35, 'icon' => 'fa-chalkboard-user', 'fasilitas' => 'AC & Proyektor', 'fasilitas_icon' => 'fa-snowflake', 'status' => 'ready'
            ],
            [
                'id' => 2, 'kategori' => 'lab', 'kategori_label' => 'Laboratorium', 
                'nama' => 'Lab Komputer A', 'deskripsi' => 'Dilengkapi 40 PC All-in-One berspesifikasi tinggi dan koneksi internet Gigabit.',
                'kapasitas' => 40, 'kapasitas_satuan' => 'PC', 'icon' => 'fa-computer', 'fasilitas' => 'Internet', 'fasilitas_icon' => 'fa-wifi', 'status' => 'booked'
            ],
            [
                'id' => 3, 'kategori' => 'aula', 'kategori_label' => 'Aula & Serbaguna', 
                'nama' => 'Gedung Aula Utama', 'deskripsi' => 'Ruangan luas untuk seminar, upacara, atau event skala besar. Sound system lengkap.',
                'kapasitas' => 300, 'icon' => 'fa-landmark', 'fasilitas' => 'Sound System', 'fasilitas_icon' => 'fa-microphone', 'status' => 'ready'
            ],
            [
                'id' => 4, 'kategori' => 'rapat', 'kategori_label' => 'Ruang Rapat', 
                'nama' => 'Meeting Room Eksekutif', 'deskripsi' => 'Meja bundar, Smart TV, dan fasilitas video conference untuk rapat penting.',
                'kapasitas' => 15, 'icon' => 'fa-handshake', 'fasilitas' => 'Smart TV', 'fasilitas_icon' => 'fa-tv', 'status' => 'ready'
            ],
            [
                'id' => 5, 'kategori' => 'lab', 'kategori_label' => 'Laboratorium', 
                'nama' => 'Lab Sains Terpadu', 'deskripsi' => 'Peralatan praktikum kimia dan biologi. Memiliki standar keamanan sirkulasi udara (fume hood).',
                'kapasitas' => 24, 'icon' => 'fa-microscope', 'fasilitas' => 'Alat Medis', 'fasilitas_icon' => 'fa-shield-virus', 'status' => 'ready'
            ],
        ]);

        return $rooms->filter(function($room) {
            $matchCategory = $this->category === 'all' || $room['kategori'] === $this->category;
            $matchSearch = empty($this->search) || stripos($room['nama'], $this->search) !== false;
            return $matchCategory && $matchSearch;
        })->values();
    }

    public function openBookingModal($room)
    {
        $this->selectedRoom = $room;
        $this->reset('form');
        $this->isBookingModalOpen = true;
    }

    public function closeBookingModal()
    {
        $this->isBookingModalOpen = false;
        $this->resetValidation();
    }

    public function submitBooking()
    {
        $this->validate([
            'form.waktu_mulai' => 'required',
            'form.waktu_selesai' => 'required',
            'form.tujuan' => 'required|string',
        ]);

        try {
            // Simulasi struktur penyimpanan mengacu pada sistem yang ada[cite: 1]
            /*
            DB::transaction(function () {
                $borrowing = Borrowing::create([
                    'kode_transaksi' => 'TRX-' . date('Ymd') . '-' . rand(1000, 9999),
                    'user_id' => auth()->id(),
                    'tujuan' => $this->form['tujuan'],
                    'tanggal_mulai' => $this->dateFilter . ' ' . $this->form['waktu_mulai'],
                    'tanggal_selesai' => $this->dateFilter . ' ' . $this->form['waktu_selesai'],
                    'status' => 'Menunggu',
                ]);

                BorrowingDetail::create([
                    'borrowing_id' => $borrowing->id,
                    'room_id' => $this->selectedRoom['id'],
                    'jumlah' => 1,
                    'status' => 'Menunggu',
                ]);
            });
            */

            $this->closeBookingModal();
            
            // Tampilkan Notifikasi Sukses
            $this->isAlertModalOpen = true;

        } catch (\Exception $e) {
            $this->addError('form', 'Terjadi kesalahan sistem.');
        }
    }
};
?>

<div class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{ alertOpen: @entangle('isAlertModalOpen'), bookingOpen: @entangle('isBookingModalOpen') }">
    
    <!-- Page Title & Controls Row -->
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white mb-2">Eksplorasi Ruangan</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Temukan dan pinjam ruangan yang sesuai dengan kebutuhan kegiatan Anda.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
            <!-- Date Picker -->
            <div class="relative w-full sm:w-48">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-regular fa-calendar text-sm"></i>
                </div>
                <input type="date" wire:model.live="dateFilter" class="w-full pl-9 pr-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all cursor-pointer">
            </div>
            
            <!-- Search Input -->
            <div class="relative w-full sm:w-64">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama ruangan..." class="w-full pl-9 pr-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
            </div>
        </div>
    </div>

    <!-- Room Type Tabs -->
    <div class="border-b border-slate-200 dark:border-slate-700/80 mb-6 relative">
        <ul class="flex gap-6 overflow-x-auto hide-scrollbar text-sm font-medium">
            <li>
                <button wire:click="$set('category', 'all')" class="{{ $category === 'all' ? 'active pb-4 border-b-2 border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'pb-4 border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }} whitespace-nowrap transition-colors">
                    <i class="fa-solid fa-border-all mr-1.5"></i> Semua Ruang
                </button>
            </li>
            <li>
                <button wire:click="$set('category', 'kelas')" class="{{ $category === 'kelas' ? 'active pb-4 border-b-2 border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'pb-4 border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }} whitespace-nowrap transition-colors">
                    <i class="fa-solid fa-chalkboard-user mr-1.5"></i> Ruang Kelas
                </button>
            </li>
            <li>
                <button wire:click="$set('category', 'lab')" class="{{ $category === 'lab' ? 'active pb-4 border-b-2 border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'pb-4 border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }} whitespace-nowrap transition-colors">
                    <i class="fa-solid fa-flask mr-1.5"></i> Laboratorium
                </button>
            </li>
            <li>
                <button wire:click="$set('category', 'aula')" class="{{ $category === 'aula' ? 'active pb-4 border-b-2 border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'pb-4 border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }} whitespace-nowrap transition-colors">
                    <i class="fa-solid fa-landmark mr-1.5"></i> Aula & Serbaguna
                </button>
            </li>
            <li>
                <button wire:click="$set('category', 'rapat')" class="{{ $category === 'rapat' ? 'active pb-4 border-b-2 border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'pb-4 border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }} whitespace-nowrap transition-colors">
                    <i class="fa-solid fa-people-group mr-1.5"></i> Ruang Rapat
                </button>
            </li>
        </ul>
    </div>

    <!-- Room Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        
        @forelse ($this->filteredRooms as $room)
            <div class="group bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden transition-all duration-300 flex flex-col {{ $room['status'] === 'booked' ? 'opacity-80' : 'hover:shadow-xl hover:shadow-brand-500/5 dark:hover:shadow-brand-900/20' }}">
                <div class="h-40 bg-slate-100 dark:bg-slate-700/50 relative overflow-hidden flex items-center justify-center">
                    <i class="fa-solid {{ $room['icon'] }} text-5xl text-slate-300 dark:text-slate-600 group-hover:scale-110 transition-transform duration-500"></i>
                    
                    @if($room['status'] === 'ready')
                        <div class="absolute top-3 right-3 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-100/90 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400 backdrop-blur-sm shadow-sm border border-emerald-200/50 dark:border-emerald-500/20">
                            <i class="fa-solid fa-circle text-[8px] mr-1"></i> Tersedia
                        </div>
                    @else
                        <div class="absolute top-3 right-3 px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-100/90 text-rose-700 dark:bg-rose-500/20 dark:text-rose-400 backdrop-blur-sm shadow-sm border border-rose-200/50 dark:border-rose-500/20">
                            <i class="fa-solid fa-clock text-[9px] mr-1"></i> Dipinjam
                        </div>
                    @endif
                </div>
                
                <div class="p-5 flex-1 flex flex-col justify-between">
                    <div>
                        <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">{{ $room['kategori_label'] }}</div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">{{ $room['nama'] }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 mb-4">{{ $room['deskripsi'] }}</p>
                        
                        <div class="flex items-center gap-3 text-xs text-slate-600 dark:text-slate-300 font-medium mb-5">
                            <span title="Kapasitas"><i class="fa-solid fa-users text-slate-400 mr-1.5"></i> {{ $room['kapasitas'] }} {{ $room['kapasitas_satuan'] ?? 'Org' }}</span>
                            <span title="Fasilitas"><i class="fa-solid {{ $room['fasilitas_icon'] }} text-slate-400 mr-1.5"></i> {{ $room['fasilitas'] }}</span>
                        </div>
                    </div>
                    
                    @if($room['status'] === 'ready')
                        <button wire:click="openBookingModal({{ json_encode($room) }})" class="w-full py-2.5 rounded-xl text-sm font-semibold text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-900/30 hover:bg-brand-600 hover:text-white dark:hover:bg-brand-600 dark:hover:text-white transition-colors">
                            Pinjam Ruang
                        </button>
                    @else
                        <button disabled class="w-full py-2.5 rounded-xl text-sm font-semibold text-slate-400 bg-slate-100 dark:bg-slate-700 dark:text-slate-500 cursor-not-allowed">
                            Tidak Tersedia
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <!-- Empty State -->
            <div class="col-span-full py-16 flex flex-col items-center justify-center text-center">
                <div class="w-20 h-20 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center text-slate-400 dark:text-slate-500 text-3xl mb-4">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-1">Tidak ada ruangan ditemukan</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Coba ubah kata kunci pencarian atau filter kategori Anda.</p>
            </div>
        @endforelse

    </div>

    <!-- Booking Modal (Alpine + Livewire) -->
    <template x-teleport="body">
        <div x-show="bookingOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300" x-cloak>
            
            <!-- Modal Background Close -->
            <div class="absolute inset-0" wire:click="closeBookingModal"></div>

            <!-- Modal Content -->
            <div x-show="bookingOpen" x-transition:enter="transition-transform duration-300" x-transition:enter-start="scale-95" x-transition:enter-end="scale-100" class="relative bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl border border-slate-100 dark:border-slate-700">
                
                @if(!empty($selectedRoom))
                <!-- Modal Header -->
                <div class="sticky top-0 bg-white/90 dark:bg-slate-800/90 backdrop-blur-md px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between z-10">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $selectedRoom['nama'] ?? '' }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2 mt-0.5">
                            <span>{{ $selectedRoom['kategori_label'] ?? '' }}</span> &bull; 
                            <i class="fa-solid fa-users text-[10px]"></i> Maks <span>{{ $selectedRoom['kapasitas'] ?? 0 }}</span> {{ $selectedRoom['kapasitas_satuan'] ?? 'Org' }}
                        </p>
                    </div>
                    <button wire:click="closeBookingModal" class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-500 hover:text-rose-500 transition-colors">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Form Peminjaman -->
                <form wire:submit.prevent="submitBooking" class="p-6 space-y-5">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Waktu Mulai <span class="text-rose-500">*</span></label>
                            <input type="time" wire:model="form.waktu_mulai" required class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Waktu Selesai <span class="text-rose-500">*</span></label>
                            <input type="time" wire:model="form.waktu_selesai" required class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Tujuan Kegiatan / Keperluan <span class="text-rose-500">*</span></label>
                        <textarea rows="3" wire:model="form.tujuan" required placeholder="Jelaskan secara singkat kegiatan yang akan dilakukan..." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 resize-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Catatan / Permintaan Fasilitas (Opsional)</label>
                        <input type="text" wire:model="form.catatan" placeholder="Contoh: Butuh tambahan 1 mic kabel..." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                    </div>

                    <div class="p-4 rounded-xl bg-indigo-50/50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800/30 flex items-start gap-3">
                        <i class="fa-solid fa-circle-info text-indigo-500 mt-0.5 text-sm"></i>
                        <div class="text-xs text-indigo-800 dark:text-indigo-300 leading-relaxed">
                            Data peminjam akan otomatis direkam sebagai <b>{{ auth()->user()->name ?? 'Pengguna' }}</b>. Pastikan Anda bertanggung jawab atas kebersihan fasilitas.
                        </div>
                    </div>

                    <div class="pt-2 flex gap-3">
                        <button type="button" wire:click="closeBookingModal" class="flex-1 py-3 rounded-xl font-medium text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 dark:text-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 transition-colors">
                            Batal
                        </button>
                        <button type="submit" class="flex-[2] py-3 rounded-xl font-semibold text-sm text-white bg-brand-600 hover:bg-brand-700 shadow-lg shadow-brand-500/30 transition-all flex items-center justify-center gap-2">
                            <span wire:loading.remove wire:target="submitBooking">Ajukan Peminjaman</span>
                            <span wire:loading wire:target="submitBooking"><i class="fa-solid fa-circle-notch animate-spin"></i> Memproses...</span>
                            <i wire:loading.remove wire:target="submitBooking" class="fa-solid fa-paper-plane text-xs"></i>
                        </button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </template>

    <!-- Custom Alert Modal (Alpine + Livewire) -->
    <template x-teleport="body">
        <div x-show="alertOpen" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm transition-opacity" x-cloak>
            <div x-show="alertOpen" x-transition:enter="transition-transform duration-200" x-transition:enter-start="scale-95" x-transition:enter-end="scale-100" class="bg-white dark:bg-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 dark:border-slate-700 text-center">
                <div class="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-500 dark:text-emerald-400 mx-auto flex items-center justify-center text-3xl mb-4">
                    <i class="fa-regular fa-circle-check"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Berhasil!</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed mb-6">
                    Permohonan peminjaman untuk <b>{{ $selectedRoom['nama'] ?? '' }}</b> berhasil dikirim. Status saat ini menunggu persetujuan (Pending).
                </p>
                <button @click="alertOpen = false" class="w-full py-2.5 rounded-xl font-semibold text-white bg-slate-800 hover:bg-slate-900 dark:bg-slate-600 dark:hover:bg-slate-500 transition-colors">
                    Oke, Mengerti
                </button>
            </div>
        </div>
    </template>

</div>