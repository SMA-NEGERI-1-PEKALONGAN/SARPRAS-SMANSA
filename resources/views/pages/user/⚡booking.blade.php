<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Room;
use App\Models\Item;
use App\Models\Borrowing;
use App\Models\BorrowingDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.user')] #[Title('Eksplorasi Peminjaman')] class extends Component 
{
    // Filter State
    public $type = 'ruangan'; // ruangan | barang
    public $dateFilter;
    public $search = '';
    public $category = 'all';

    // Modal & Form State
    public $isBookingModalOpen = false;
    public $isAlertModalOpen = false;
    public $isInfoModalOpen = false;
    
    public $selectedItem = [];
    
    public $form = [
        'waktu_mulai' => '',
        'waktu_selesai' => '',
        'jumlah' => 1,
        'tujuan' => '',
        'catatan' => ''
    ];

    public function mount()
    {
        $this->dateFilter = now()->format('Y-m-d');
    }

    public function updatingSearch() { }
    public function updatingCategory() { }
    public function updatingType() 
    {
        $this->category = 'all'; // Reset kategori saat pindah tipe
        $this->search = '';
    }

    // Properti untuk memuat data Ruang atau Barang
    public function getFilteredDataProperty()
    {
        if ($this->type === 'ruangan') {
            $data = collect([
                [
                    'id' => 1, 'tipe' => 'ruangan', 'kategori' => 'kelas', 'kategori_label' => 'Ruang Kelas', 
                    'nama' => 'Kelas Teori 2.01', 'deskripsi' => 'Ruang kelas ber-AC dengan proyektor.',
                    'kapasitas' => 35, 'satuan' => 'Orang', 'icon' => 'fa-chalkboard-user', 'fasilitas' => 'AC & Proyektor', 'fasilitas_icon' => 'fa-snowflake', 'status' => 'ready'
                ],
                [
                    'id' => 2, 'tipe' => 'ruangan', 'kategori' => 'lab', 'kategori_label' => 'Laboratorium', 
                    'nama' => 'Lab Komputer A', 'deskripsi' => 'Dilengkapi 40 PC All-in-One.',
                    'kapasitas' => 40, 'satuan' => 'PC', 'icon' => 'fa-computer', 'fasilitas' => 'Internet', 'fasilitas_icon' => 'fa-wifi', 'status' => 'booked'
                ],
                [
                    'id' => 3, 'tipe' => 'ruangan', 'kategori' => 'rapat', 'kategori_label' => 'Ruang Rapat', 
                    'nama' => 'Meeting Room Eksekutif', 'deskripsi' => 'Fasilitas video conference.',
                    'kapasitas' => 15, 'satuan' => 'Orang', 'icon' => 'fa-handshake', 'fasilitas' => 'Smart TV', 'fasilitas_icon' => 'fa-tv', 'status' => 'ready'
                ],
            ]);
        } else {
            $data = collect([
                [
                    'id' => 101, 'tipe' => 'barang', 'kategori' => 'elektronik', 'kategori_label' => 'Elektronik', 
                    'nama' => 'Proyektor Epson X500', 'deskripsi' => 'Proyektor portabel 3000 Lumens.',
                    'kapasitas' => 5, 'satuan' => 'Unit', 'icon' => 'fa-video', 'fasilitas' => 'Kabel HDMI/VGA', 'fasilitas_icon' => 'fa-plug', 'status' => 'ready'
                ],
                [
                    'id' => 102, 'tipe' => 'barang', 'kategori' => 'elektronik', 'kategori_label' => 'Audio', 
                    'nama' => 'Speaker Portable JBL', 'deskripsi' => 'Speaker bluetooth dengan 2 mic wireless.',
                    'kapasitas' => 2, 'satuan' => 'Set', 'icon' => 'fa-volume-high', 'fasilitas' => 'Baterai Cas', 'fasilitas_icon' => 'fa-battery-full', 'status' => 'ready'
                ],
            ]);
        }

        return $data->filter(function($item) {
            $matchCategory = $this->category === 'all' || $item['kategori'] === $this->category;
            $matchSearch = empty($this->search) || stripos($item['nama'], $this->search) !== false;
            return $matchCategory && $matchSearch;
        })->values();
    }

    // Properti untuk memuat jadwal yang sudah ter-booking pada tanggal yang dipilih
    public function getBookedScheduleProperty()
    {
        return collect([
            [
                'waktu' => '08:00 - 10:30',
                'peminjam' => 'BEM Fakultas',
                'item' => 'Lab Komputer A',
                'tujuan' => 'Pelatihan Desain Grafis',
                'status' => 'Disetujui'
            ],
            [
                'waktu' => '13:00 - 15:00',
                'peminjam' => 'Dosen (Bpk. Ahmad)',
                'item' => 'Proyektor Epson X500',
                'tujuan' => 'Seminar Proposal Mahasiswa',
                'status' => 'Digunakan'
            ],
        ]);
    }

    public function openBookingModal($item)
    {
        $this->selectedItem = $item;
        $this->reset('form');
        $this->form['jumlah'] = 1;
        $this->isBookingModalOpen = true;
    }

    public function closeBookingModal()
    {
        $this->isBookingModalOpen = false;
        $this->resetValidation();
    }

    public function submitBooking()
    {
        $rules = [
            'form.waktu_mulai' => 'required',
            'form.waktu_selesai' => 'required',
            'form.tujuan' => 'required|string',
        ];

        if ($this->selectedItem['tipe'] === 'barang') {
            $rules['form.jumlah'] = 'required|integer|min:1|max:' . $this->selectedItem['kapasitas'];
        }

        $this->validate($rules);

        try {
            // Simulasi query simpan
            $this->closeBookingModal();
            $this->isAlertModalOpen = true;
        } catch (\Exception $e) {
            $this->addError('form', 'Terjadi kesalahan sistem.');
        }
    }
};
?>

<div class="flex-1 w-full mt-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{ alertOpen: @entangle('isAlertModalOpen'), bookingOpen: @entangle('isBookingModalOpen'), infoOpen: @entangle('isInfoModalOpen') }">
    <br>
    <!-- Page Title & Controls Row -->
    <div class="flex flex-col xl:flex-row xl:items-end justify-between gap-6 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white mb-2">Eksplorasi Peminjaman</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Cari, periksa jadwal, dan ajukan peminjaman fasilitas.</p>
        </div>
        
        <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto">
            
            <!-- Filter Tipe (Barang / Ruangan) dengan Desain Menarik -->
            <div class="relative w-full sm:w-auto">
                <select wire:model.live="type" class="w-full sm:w-48 appearance-none pl-10 pr-8 py-2.5 bg-brand-50 border border-brand-200 text-brand-700 dark:bg-brand-900/30 dark:border-brand-800 dark:text-brand-300 rounded-xl text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer transition-colors shadow-sm">
                    <option value="ruangan">🏢 Pinjam Ruangan</option>
                    <option value="barang">📦 Pinjam Barang</option>
                </select>
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-brand-500">
                    <i class="fa-solid fa-layer-group text-sm"></i>
                </div>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-brand-500">
                    <i class="fa-solid fa-chevron-down text-xs"></i>
                </div>
            </div>

            <!-- Date Picker -->
            <div class="relative w-full sm:w-40 md:w-44 lg:w-48">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-regular fa-calendar text-sm"></i>
                </div>
                <input type="date" wire:model.live="dateFilter" class="w-full pl-9 pr-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition-all cursor-pointer shadow-sm">
            </div>
            
            <!-- Search Input -->
            <div class="relative w-full sm:w-56 md:w-64 lg:w-72 flex-1 sm:flex-none">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari {{ $type === 'ruangan' ? 'ruangan' : 'barang' }}..." class="w-full pl-9 pr-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition-all shadow-sm">
            </div>

            <!-- Tombol Info Jadwal (Sesuai Admin) -->
            <button wire:click="$set('isInfoModalOpen', true)" class="w-full sm:w-auto px-4 py-2.5 bg-slate-800 hover:bg-slate-900 text-white dark:bg-slate-700 dark:hover:bg-slate-600 rounded-xl text-sm font-semibold transition-colors shadow-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-circle-info text-brand-400"></i> Info Jadwal
            </button>

        </div>
    </div>

    <!-- Dynamic Category Tabs -->
    <div class="border-b border-slate-200 dark:border-slate-700/80 mb-6 relative">
        <ul class="flex gap-6 overflow-x-auto hide-scrollbar text-sm font-medium">
            <li>
                <button wire:click="$set('category', 'all')" class="{{ $category === 'all' ? 'active pb-4 border-b-2 border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'pb-4 border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }} whitespace-nowrap transition-colors">
                    <i class="fa-solid fa-border-all mr-1.5"></i> Semua
                </button>
            </li>
            
            @if($type === 'ruangan')
                <li>
                    <button wire:click="$set('category', 'kelas')" class="{{ $category === 'kelas' ? 'active pb-4 border-b-2 border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'pb-4 border-b-2 border-transparent text-slate-500 hover:text-slate-700' }} whitespace-nowrap transition-colors">
                        <i class="fa-solid fa-chalkboard-user mr-1.5"></i> Kelas
                    </button>
                </li>
                <li>
                    <button wire:click="$set('category', 'lab')" class="{{ $category === 'lab' ? 'active pb-4 border-b-2 border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'pb-4 border-b-2 border-transparent text-slate-500 hover:text-slate-700' }} whitespace-nowrap transition-colors">
                        <i class="fa-solid fa-flask mr-1.5"></i> Laboratorium
                    </button>
                </li>
                <li>
                    <button wire:click="$set('category', 'rapat')" class="{{ $category === 'rapat' ? 'active pb-4 border-b-2 border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'pb-4 border-b-2 border-transparent text-slate-500 hover:text-slate-700' }} whitespace-nowrap transition-colors">
                        <i class="fa-solid fa-handshake mr-1.5"></i> Ruang Rapat
                    </button>
                </li>
            @else
                <li>
                    <button wire:click="$set('category', 'elektronik')" class="{{ $category === 'elektronik' ? 'active pb-4 border-b-2 border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'pb-4 border-b-2 border-transparent text-slate-500 hover:text-slate-700' }} whitespace-nowrap transition-colors">
                        <i class="fa-solid fa-plug mr-1.5"></i> Elektronik / IT
                    </button>
                </li>
                <li>
                    <button wire:click="$set('category', 'mebel')" class="{{ $category === 'mebel' ? 'active pb-4 border-b-2 border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'pb-4 border-b-2 border-transparent text-slate-500 hover:text-slate-700' }} whitespace-nowrap transition-colors">
                        <i class="fa-solid fa-chair mr-1.5"></i> Mebel & Logistik
                    </button>
                </li>
            @endif
        </ul>
    </div>

    <!-- Data Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        
        @forelse ($this->filteredData as $item)
            <div class="group bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden transition-all duration-300 flex flex-col {{ $item['status'] === 'booked' ? 'opacity-80' : 'hover:shadow-xl hover:shadow-brand-500/5 dark:hover:shadow-brand-900/20' }}">
                <div class="h-40 bg-slate-100 dark:bg-slate-700/50 relative overflow-hidden flex items-center justify-center">
                    <i class="fa-solid {{ $item['icon'] }} text-5xl text-slate-300 dark:text-slate-600 group-hover:scale-110 transition-transform duration-500"></i>
                    
                    @if($item['status'] === 'ready')
                        <div class="absolute top-3 right-3 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-100/90 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400 backdrop-blur-sm shadow-sm border border-emerald-200/50">
                            <i class="fa-solid fa-circle text-[8px] mr-1"></i> Tersedia
                        </div>
                    @else
                        <div class="absolute top-3 right-3 px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-100/90 text-rose-700 dark:bg-rose-500/20 dark:text-rose-400 backdrop-blur-sm shadow-sm border border-rose-200/50">
                            <i class="fa-solid fa-clock text-[9px] mr-1"></i> Dipinjam
                        </div>
                    @endif
                </div>
                
                <div class="p-5 flex-1 flex flex-col justify-between">
                    <div>
                        <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">{{ $item['kategori_label'] }}</div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">{{ $item['nama'] }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 mb-4">{{ $item['deskripsi'] }}</p>
                        
                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-600 dark:text-slate-300 font-medium mb-5">
                            <span class="bg-slate-50 dark:bg-slate-900/50 px-2 py-1 rounded-md border border-slate-100 dark:border-slate-700" title="{{ $type === 'ruangan' ? 'Kapasitas Ruang' : 'Stok Tersedia' }}">
                                <i class="fa-solid {{ $type === 'ruangan' ? 'fa-users' : 'fa-box' }} text-slate-400 mr-1.5"></i> {{ $item['kapasitas'] }} {{ $item['satuan'] }}
                            </span>
                            <span class="bg-slate-50 dark:bg-slate-900/50 px-2 py-1 rounded-md border border-slate-100 dark:border-slate-700">
                                <i class="fa-solid {{ $item['fasilitas_icon'] }} text-slate-400 mr-1.5"></i> {{ $item['fasilitas'] }}
                            </span>
                        </div>
                    </div>
                    
                    @if($item['status'] === 'ready')
                        <button wire:click="openBookingModal({{ json_encode($item) }})" class="w-full py-2.5 rounded-xl text-sm font-semibold text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-900/30 hover:bg-brand-600 hover:text-white dark:hover:bg-brand-600 transition-colors">
                            Pinjam {{ $type === 'ruangan' ? 'Ruangan' : 'Barang' }}
                        </button>
                    @else
                        <button disabled class="w-full py-2.5 rounded-xl text-sm font-semibold text-slate-400 bg-slate-100 dark:bg-slate-700 cursor-not-allowed">
                            Tidak Tersedia
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 flex flex-col items-center justify-center text-center">
                <div class="w-20 h-20 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center text-slate-400 dark:text-slate-500 text-3xl mb-4">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-1">Data tidak ditemukan</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Coba ubah kata kunci pencarian atau filter kategori Anda.</p>
            </div>
        @endforelse

    </div>

    <!-- Info Jadwal Modal -->
    <template x-teleport="body">
        <div x-show="infoOpen" class="fixed inset-0 z-[55] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300" x-cloak>
            <div class="absolute inset-0" wire:click="$set('isInfoModalOpen', false)"></div>
            <div x-show="infoOpen" x-transition:enter="transition-transform duration-300" x-transition:enter-start="scale-95" x-transition:enter-end="scale-100" class="relative bg-white dark:bg-slate-800 rounded-2xl w-full max-w-4xl max-h-[90vh] flex flex-col shadow-2xl border border-slate-100 dark:border-slate-700">
                
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between z-10">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-calendar-day text-brand-500"></i> Jadwal Peminjaman
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Filter Tanggal: {{ \Carbon\Carbon::parse($dateFilter)->translatedFormat('l, d F Y') }}</p>
                    </div>
                    <button wire:click="$set('isInfoModalOpen', false)" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-500 hover:text-rose-500 transition-colors">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto">
                    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                        <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                            <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs uppercase text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="px-4 py-3">Waktu</th>
                                    <th class="px-4 py-3">Peminjam</th>
                                    <th class="px-4 py-3">Barang / Ruangan</th>
                                    <th class="px-4 py-3">Tujuan</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                @forelse($this->bookedSchedule as $schedule)
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                                        <td class="px-4 py-3 whitespace-nowrap font-medium text-slate-800 dark:text-slate-200">{{ $schedule['waktu'] }}</td>
                                        <td class="px-4 py-3 font-medium">{{ $schedule['peminjam'] }}</td>
                                        <td class="px-4 py-3">{{ $schedule['item'] }}</td>
                                        <td class="px-4 py-3">{{ $schedule['tujuan'] }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide {{ $schedule['status'] === 'Disetujui' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400' }}">
                                                {{ $schedule['status'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                            Belum ada jadwal peminjaman pada tanggal ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Booking Modal -->
    <template x-teleport="body">
        <div x-show="bookingOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300" x-cloak>
            <div class="absolute inset-0" wire:click="closeBookingModal"></div>
            <div x-show="bookingOpen" x-transition:enter="transition-transform duration-300" x-transition:enter-start="scale-95" x-transition:enter-end="scale-100" class="relative bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl border border-slate-100 dark:border-slate-700">
                
                @if(!empty($selectedItem))
                <div class="sticky top-0 bg-white/90 dark:bg-slate-800/90 backdrop-blur-md px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between z-10">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $selectedItem['nama'] ?? '' }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2 mt-0.5">
                            <span class="uppercase tracking-wide font-semibold">{{ $selectedItem['kategori_label'] ?? '' }}</span> &bull; 
                            <i class="fa-solid {{ $selectedItem['tipe'] === 'ruangan' ? 'fa-users' : 'fa-box' }} text-[10px]"></i> 
                            Maks <span>{{ $selectedItem['kapasitas'] ?? 0 }}</span> {{ $selectedItem['satuan'] ?? '' }}
                        </p>
                    </div>
                    <button wire:click="closeBookingModal" class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-500 hover:text-rose-500 transition-colors">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

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

                    @if($selectedItem['tipe'] === 'barang')
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Jumlah Barang <span class="text-rose-500">*</span></label>
                        <input type="number" wire:model="form.jumlah" required min="1" max="{{ $selectedItem['kapasitas'] }}" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                        @error('form.jumlah') <span class="text-[10px] text-rose-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Tujuan Kegiatan <span class="text-rose-500">*</span></label>
                        <textarea rows="3" wire:model="form.tujuan" required placeholder="Jelaskan secara singkat tujuan peminjaman..." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 resize-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Catatan Tambahan (Opsional)</label>
                        <input type="text" wire:model="form.catatan" placeholder="Contoh: Titip di pos satpam..." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                    </div>

                    <div class="pt-2 flex gap-3">
                        <button type="button" wire:click="closeBookingModal" class="flex-1 py-3 rounded-xl font-medium text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 dark:text-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 transition-colors">
                            Batal
                        </button>
                        <button type="submit" class="flex-[2] py-3 rounded-xl font-semibold text-sm text-white bg-brand-600 hover:bg-brand-700 shadow-lg shadow-brand-500/30 transition-all flex items-center justify-center gap-2">
                            <span wire:loading.remove wire:target="submitBooking">Ajukan Peminjaman</span>
                            <span wire:loading wire:target="submitBooking"><i class="fa-solid fa-circle-notch animate-spin"></i> Memproses...</span>
                        </button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </template>

    <!-- Alert Modal (sama dengan sebelumnya) -->
    <template x-teleport="body">
        <div x-show="alertOpen" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm transition-opacity" x-cloak>
            <div x-show="alertOpen" x-transition:enter="transition-transform duration-200" x-transition:enter-start="scale-95" x-transition:enter-end="scale-100" class="bg-white dark:bg-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl text-center">
                <div class="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-500 dark:text-emerald-400 mx-auto flex items-center justify-center text-3xl mb-4">
                    <i class="fa-regular fa-circle-check"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Berhasil!</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed mb-6">
                    Peminjaman <b>{{ $selectedItem['nama'] ?? '' }}</b> berhasil diajukan.
                </p>
                <button @click="alertOpen = false" class="w-full py-2.5 rounded-xl font-semibold text-white bg-slate-800 hover:bg-slate-900 dark:bg-slate-600 dark:hover:bg-slate-500 transition-colors">
                    Tutup
                </button>
            </div>
        </div>
    </template>

</div>