<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use App\Models\Room;
use App\Models\Item;
use App\Models\User;
use App\Models\Borrowing;
use App\Models\BorrowingDetail;
use Carbon\Carbon;
new #[Layout('layouts.app')] #[Title('Dashboard Peminjaman Grid')] class extends Component 
{
    use WithPagination;

    // Filter state
    public $search = '';
    public $dateFrom;
    public $dateTo;
    public $tab = 'room'; // 'room' atau 'item'

    // Modal state
    public $isDetailModalOpen = false;
    public $selectedResourceName = '';
    public $selectedResourceId = null;
    public $selectedResourceType = 'room'; // 'room' atau 'item'
    public $activeBorrowings = [];


    // --- TAMBAHKAN STATE UNTUK MODAL TAMBAH BOOKING ---
    public $isAddModalOpen = false;
    public $formUserId = '';
    public $formType = 'room'; // 'room' atau 'item'
    public $formResourceId = '';
    public $formJumlah = 1;
    public $formTujuan = '';
    public $formDateFrom = '';
    public $formDateTo = '';

    public $form = [
        'user_id' => '',
        'tanggal_mulai' => '',
        'tanggal_selesai' => '',
        'tujuan' => '',
        'rooms' => [
            ['room_id' => '']
        ],
        'items' => [  // <--- INI BENAR
            ['item_id' => '', 'jumlah' => 1]
        ]
    ];

    public function mount()
    {
        $this->dateFrom = now()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingDateFrom() { $this->resetPage(); }
    public function updatingDateTo() { $this->resetPage(); }
    public function updatingTab() { $this->resetPage(); }

    public function with(): array
    {
        $startDate = Carbon::parse($this->dateFrom)->startOfDay();
        $endDate = Carbon::parse($this->dateTo)->endOfDay();

        if ($this->tab === 'room') {
            $query = Room::query()
                ->when($this->search, fn($q) => $q->where('nama_ruangan', 'like', "%{$this->search}%")->orWhere('kode_ruangan', 'like', "%{$this->search}%"))
                ->withCount([
                    'borrowingDetails as pending_count' => function ($q) use ($startDate, $endDate) {
                        $q->where('status', 'Menunggu')
                          ->whereHas('borrowing', fn($b) => $b->whereBetween('tanggal_mulai', [$startDate, $endDate]));
                    },
                    'borrowingDetails as total_active_count' => function ($q) use ($startDate, $endDate) {
                        $q->whereIn('status', ['Menunggu', 'Disetujui', 'Dipinjam'])
                          ->whereHas('borrowing', fn($b) => $b->whereBetween('tanggal_mulai', [$startDate, $endDate]));
                    }
                ]);
        } else {
            $query = Item::query()
                ->when($this->search, fn($q) => $q->where('nama_barang', 'like', "%{$this->search}%")->orWhere('kode_barang', 'like', "%{$this->search}%"))
                ->withCount([
                    'borrowingDetails as pending_count' => function ($q) use ($startDate, $endDate) {
                        $q->where('status', 'Menunggu')
                          ->whereHas('borrowing', fn($b) => $b->whereBetween('tanggal_mulai', [$startDate, $endDate]));
                    },
                    'borrowingDetails as total_active_count' => function ($q) use ($startDate, $endDate) {
                        $q->whereIn('status', ['Menunggu', 'Disetujui', 'Dipinjam'])
                          ->whereHas('borrowing', fn($b) => $b->whereBetween('tanggal_mulai', [$startDate, $endDate]));
                    }
                ]);
        }

        return [
            'resources' => $query->paginate(9),
            'usersList' => User::orderBy('name')->get(),
            'roomsList' => Room::where('status_tersedia', true)->get(),
            'itemsList' => Item::where('bisa_dipinjam', true)->get(),
            
        ];
    }

    public function openAddModal()
    {
        $this->reset('form');
        $this->form['rooms'] = [['room_id' => '']];
        $this->form['items'] = [['item_id' => '', 'jumlah' => 1]];
        $this->isAddModalOpen = true;
    }

    public function closeAddModal()
    {
        $this->isAddModalOpen = false;
        $this->resetValidation();
    }

    // Handler Baris Ruangan
    public function addRoomRow()
    {
        $this->form['rooms'][] = ['room_id' => ''];
    }

    public function removeRoomRow($index)
    {
        if (count($this->form['rooms']) > 1) {
            unset($this->form['rooms'][$index]);
            $this->form['rooms'] = array_values($this->form['rooms']);
        }
    }

    // Handler Baris Barang
    public function addItemRow()
    {
        $this->form['items'][] = ['item_id' => '', 'jumlah' => 1];
    }

    public function removeItemRow($index)
    {
        if (count($this->form['items']) > 1) {
            unset($this->form['items'][$index]);
            $this->form['items'] = array_values($this->form['items']);
        }
    }

    public function saveBooking()
    {
        // 1. Filter baris ruangan & barang yang belum dipilih/kosong
        $this->form['rooms'] = array_values(array_filter($this->form['rooms'], function ($room) {
            return !empty($room['room_id']);
        }));

        $this->form['items'] = array_values(array_filter($this->form['items'], function ($item) {
            return !empty($item['item_id']);
        }));

        // Minimal harus pilih 1 ruangan ATAU 1 barang
        if (empty($this->form['rooms']) && empty($this->form['items'])) {
            $this->addError('form.rooms', 'Pilih minimal satu ruangan atau satu barang.');
            return;
        }

        // 2. Validasi Data
        $this->validate([
            'form.user_id' => 'required|exists:users,id',
            'form.tanggal_mulai' => 'required|date',
            'form.tanggal_selesai' => 'required|date|after_or_equal:form.tanggal_mulai',
            'form.tujuan' => 'required|string',
            'form.rooms.*.room_id' => 'required|exists:rooms,id',
            'form.items.*.item_id' => 'required|exists:items,id',
            'form.items.*.jumlah' => 'required|integer|min:1',
        ]);

        // 3. Simpan Ke Database menggunakan Transaction
        try {
            DB::transaction(function () {
                // Insert Header Transaksi
                $borrowing = Borrowing::create([
                    'kode_transaksi' => 'TRX-' . date('Ymd') . '-' . rand(1000, 9999),
                    'user_id' => $this->form['user_id'],
                    'approved_by' => auth()->id() ?? $this->form['user_id'],
                    'tujuan' => $this->form['tujuan'],
                    'tanggal_mulai' => $this->form['tanggal_mulai'],
                    'tanggal_selesai' => $this->form['tanggal_selesai'],
                    'status' => 'Disetujui',
                ]);

                // Insert Detail Ruangan
                foreach ($this->form['rooms'] as $room) {
                    BorrowingDetail::create([
                        'borrowing_id' => $borrowing->id,
                        'room_id' => $room['room_id'],
                        'item_id' => null,
                        'jumlah' => 1,
                        'status' => 'Disetujui',
                    ]);
                }

                // Insert Detail Barang
                foreach ($this->form['items'] as $item) {
                    BorrowingDetail::create([
                        'borrowing_id' => $borrowing->id,
                        'room_id' => null,
                        'item_id' => $item['item_id'],
                        'jumlah' => $item['jumlah'],
                        'status' => 'Disetujui',
                    ]);
                }
            });

            $this->closeAddModal();
            $this->dispatch('toast', type: 'success', message: 'Booking berhasil disimpan!');

        } catch (\Exception $e) {
            // Tampilkan error jika terjadi kegagalan insert
            dd($e->getMessage()); 
        }
    }
    // Modal list peminjam per card
    public function openDetailModal(string $type, int $id, string $name)
    {
        $this->selectedResourceType = $type;
        $this->selectedResourceId = $id;
        $this->selectedResourceName = $name;
        $this->loadBorrowingData();
        $this->isDetailModalOpen = true;
    }

    public function loadBorrowingData()
    {
        $startDate = Carbon::parse($this->dateFrom)->startOfDay();
        $endDate = Carbon::parse($this->dateTo)->endOfDay();

        $column = $this->selectedResourceType === 'room' ? 'room_id' : 'item_id';

        $this->activeBorrowings = BorrowingDetail::with(['borrowing.user'])
            ->where($column, $this->selectedResourceId)
            ->whereHas('borrowing', function($q) use ($startDate, $endDate) {
                $q->whereBetween('tanggal_mulai', [$startDate, $endDate]);
            })
            ->get()
            ->map(function($detail) {
                return [
                    'id' => $detail->id,
                    'borrowing_id' => $detail->borrowing_id,
                    'kode_transaksi' => $detail->borrowing->kode_transaksi,
                    'peminjam' => $detail->borrowing->user->name ?? 'N/A',
                    'no_wa' => $detail->borrowing->user->no_hp ?? '',
                    'tujuan' => $detail->borrowing->tujuan,
                    'tanggal_mulai' => $detail->borrowing->tanggal_mulai->format('d M Y H:i'),
                    'tanggal_selesai' => $detail->borrowing->tanggal_selesai->format('d M Y H:i'),
                    'jumlah' => $detail->jumlah,
                    'status' => $detail->status,
                ];
            })
            ->toArray();
    }

    // Update status detail peminjaman
    public function updateStatus(int $detailId, string $status)
    {
        $detail = BorrowingDetail::findOrFail($detailId);
        $detail->update(['status' => $status]);

        // Cek update status induk jika perlu
        $header = Borrowing::find($detail->borrowing_id);
        if ($header) {
            $allDetails = $header->details;
            if ($allDetails->every(fn($d) => $d->status === 'Disetujui')) {
                $header->update(['status' => 'Disetujui', 'approved_by' => auth()->id()]);
            } elseif ($allDetails->every(fn($d) => $d->status === 'Ditolak')) {
                $header->update(['status' => 'Ditolak', 'approved_by' => auth()->id()]);
            }
        }

        $this->loadBorrowingData();
        $this->dispatch('toast', type: 'success', message: "Status peminjaman berhasil diperbarui ke $status!");
    }

    public function closeModal()
    {
        $this->isDetailModalOpen = false;
    }
};
?>

<div>
    {{-- Top Header Section --}}
    <div class="flex flex-col gap-4 mb-8 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Dashboard Peminjaman</h1>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Monitoring status ketersediaan & pengajuan peminjaman realtime.</p>
        </div>

        {{-- Switch Tab Ruangan / Barang --}}
        <div class="flex items-center gap-2 p-1 bg-gray-100 rounded-2xl dark:bg-gray-800">
            <button wire:click="openAddModal" class="px-5 py-2 text-xs font-bold text-white transition-all bg-emerald-600 rounded-xl hover:bg-emerald-700 shadow-md shadow-emerald-500/20">
                <i class="mr-1 fa-solid fa-plus"></i> Tambah Booking
            </button>
            <button wire:click="$set('tab', 'room')" class="px-5 py-2 text-xs font-bold rounded-xl transition-all {{ $tab === 'room' ? 'bg-white text-indigo-600 shadow-md dark:bg-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400' }}">
                <i class="mr-1.5 fa-solid fa-door-open"></i> Ruangan
            </button>
            <button wire:click="$set('tab', 'item')" class="px-5 py-2 text-xs font-bold rounded-xl transition-all {{ $tab === 'item' ? 'bg-white text-indigo-600 shadow-md dark:bg-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400' }}">
                <i class="mr-1.5 fa-solid fa-box-open"></i> Barang / Inventaris
            </button>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="p-6 mb-8 transition-all bg-white border border-gray-200 shadow-sm rounded-3xl dark:bg-gray-900 dark:border-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
            
            {{-- Search Input --}}
            <div class="relative md:col-span-5">
                <i class="absolute left-4 top-1/2 z-10 -translate-y-1/2 text-gray-400 fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50
                        py-3 pl-11 pr-4
                        text-sm font-medium text-slate-700
                        placeholder:text-slate-400
                        shadow-sm
                        outline-none
                        transition-all duration-200
                        hover:border-slate-300 hover:bg-white
                        focus:border-indigo-500 focus:bg-white
                        focus:ring-4 focus:ring-indigo-500/10
                        dark:border-slate-700
                        dark:bg-slate-800
                        dark:text-white
                        dark:placeholder:text-slate-500
                        dark:hover:border-slate-600
                        dark:focus:border-indigo-500"
                    placeholder="Cari {{ $tab === 'room' ? 'nama / kode ruangan' : 'nama / kode barang' }}..."
                >
            </div>

            {{-- Range Tanggal --}}
            <div class="flex items-center gap-2 md:col-span-7">
                <div class="flex-1">
                    <label class="block mb-1 text-[10px] font-bold uppercase text-gray-400">Dari Tanggal</label>
                    <input type="date" wire:model.live="dateFrom" class="w-full px-3 py-2 text-xs font-bold transition-all border-none outline-none bg-gray-50 dark:bg-gray-800 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white">
                </div>
                <span class="mt-4 text-gray-400">-</span>
                <div class="flex-1">
                    <label class="block mb-1 text-[10px] font-bold uppercase text-gray-400">Sampai Tanggal</label>
                    <input type="date" wire:model.live="dateTo" class="w-full px-3 py-2 text-xs font-bold transition-all border-none outline-none bg-gray-50 dark:bg-gray-800 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white">
                </div>
            </div>
        </div>
    </div>

    {{-- Grid Content --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($resources as $res)
            @php
                $name = $tab === 'room' ? $res->nama_ruangan : $res->nama_barang;
                $code = $tab === 'room' ? $res->kode_ruangan : $res->kode_barang;
                $capacity = $tab === 'room' ? $res->kapasitas . ' Orang' : $res->jumlah_total . ' Unit';
                $hasPending = $res->pending_count > 0;
            @endphp

            <div class="relative flex flex-col justify-between transition-all bg-white border border-gray-200 shadow-sm rounded-3xl dark:bg-gray-900 dark:border-gray-800 hover:shadow-lg hover:border-indigo-200 dark:hover:border-indigo-900">
                
                {{-- Top Card Header --}}
                <div class="p-6 border-b border-gray-100 dark:border-gray-800/60">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center justify-center text-indigo-600 rounded-2xl w-14 h-14 bg-indigo-50 dark:bg-indigo-900/30 dark:text-indigo-400">
                                <i class="text-2xl {{ $res->icon ?: ($tab === 'room' ? 'fa-solid fa-door-closed' : 'fa-solid fa-box') }}"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 text-md dark:text-white">{{ $name }}</h3>
                                <span class="inline-block px-2.5 py-0.5 text-[10px] font-bold text-indigo-600 bg-indigo-50 rounded-md dark:bg-indigo-950 dark:text-indigo-300">
                                    {{ $code }}
                                </span>
                            </div>
                        </div>

                        {{-- Tombol Info Status (Berkedip Jika Ada Pending Approval) --}}
                        <div class="relative">
                            @if ($hasPending)
                                <span class="absolute top-0 right-0 flex w-3 h-3 -mt-1 -mr-1">
                                    <span class="absolute inline-flex w-full h-full rounded-full opacity-75 animate-ping bg-amber-400"></span>
                                    <span class="relative inline-flex w-3 h-3 rounded-full bg-amber-500"></span>
                                </span>
                            @endif

                            <button wire:click="openDetailModal('{{ $tab }}', {{ $res->id }}, '{{ addslashes($name) }}')" title="{{ $hasPending ? 'Ada Pengajuan Menunggu Approval!' : 'Info Peminjaman' }}" class="flex items-center justify-center transition-colors rounded-xl w-9 h-9 {{ $hasPending ? 'bg-amber-100 text-amber-700 hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400' }}">
                                <i class="fa-solid fa-bell text-xs font-bold {{ $hasPending ? 'animate-bounce' : '' }}"></i>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Middle Card Stats --}}
                <div class="p-6 space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-semibold text-gray-400 uppercase">Kapasitas / Stok</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $capacity }}</span>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <span class="font-semibold text-gray-400 uppercase">Total Agenda</span>
                        <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ $res->total_active_count }} Pengajuan</span>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <span class="font-semibold text-gray-400 uppercase">Menunggu Approve</span>
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $hasPending ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-800' }}">
                            {{ $res->pending_count }} Antrean
                        </span>
                    </div>
                </div>

                {{-- Card Action Footer --}}
                <div class="p-4 border-t border-gray-100 bg-gray-50/50 rounded-b-3xl dark:border-gray-800/60 dark:bg-gray-800/20">
                    <button wire:click="openDetailModal('{{ $tab }}', {{ $res->id }}, '{{ addslashes($name) }}')" class="flex items-center justify-center w-full gap-2 px-4 py-2.5 text-xs font-bold text-white transition-all bg-indigo-600 shadow-md rounded-2xl hover:bg-indigo-700 shadow-indigo-500/20">
                        <i class="fa-solid fa-list-check"></i> Data Peminjaman
                    </button>
                </div>

            </div>
        @empty
            <div class="col-span-full py-16 text-center bg-white border border-gray-200 shadow-sm rounded-3xl dark:bg-gray-900 dark:border-gray-800">
                <i class="mb-3 text-4xl text-gray-300 fa-solid fa-folder-open dark:text-gray-700"></i>
                <p class="text-sm font-semibold text-gray-400">Tidak ada data {{ $tab === 'room' ? 'ruangan' : 'barang' }} ditemukan.</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-8">
        {{ $resources->links() }}
    </div>

    {{-- MODAL TAMBAH BOOKING MULTI RUANG & ITEM --}}
    <section x-data="{ openAdd: @entangle('isAddModalOpen') }">
        <template x-teleport="body">
            <div x-show="openAdd" class="fixed inset-0 z-[10001] flex items-center justify-center p-4" x-cloak>
                <div x-show="openAdd" x-transition.opacity wire:click="closeAddModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
                
                <div x-show="openAdd" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0" class="relative w-full max-w-5xl bg-white dark:bg-gray-900 rounded-[2rem] p-8 border border-gray-100 dark:border-gray-800 shadow-2xl overflow-y-auto max-h-[90vh]">
                    
                    <div class="flex items-center justify-between pb-4 mb-6 border-b border-gray-100 dark:border-gray-800">
                        <h4 class="text-xl font-bold text-gray-900 dark:text-white">Form Transaksi Peminjaman (Multi Room & Item)</h4>
                        <button type="button" wire:click="closeAddModal" class="p-1 text-gray-400 transition-colors rounded-lg hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800">
                            <i class="ti ti-x"></i>
                        </button>
                    </div>
                    
                    <form wire:submit="saveBooking" class="space-y-6 text-sm">
                        
                        {{-- HEADER / MASTER DATA --}}
                        <div class="grid grid-cols-1 gap-4 p-5 border border-gray-100 md:grid-cols-3 bg-gray-50 dark:bg-gray-800/50 rounded-2xl dark:border-gray-800">
                            {{-- Select2 User --}}
                            <div class="md:col-span-3">
                                <label class="block font-bold text-gray-700 dark:text-gray-300">Peminjam / User</label>
                                <!-- Perhatikan penambahan class="relative w-full mt-1" -->
                                <div wire:ignore wire:key="select-user-container" class="relative w-full mt-1" x-data="{
                                    init() {
                                        this.$nextTick(() => {
                                            let el = $(this.$refs.selectUser).select2({
                                                placeholder: '-- Pilih Peminjam --',
                                                width: '100%',
                                                dropdownParent: $(this.$el)
                                            });
                                            el.on('change', (e) => { $wire.set('form.user_id', e.target.value); });
                                            $watch('$wire.form.user_id', (val) => { el.val(val).trigger('change.select2'); });
                                        });
                                    }
                                }">
                                    <select x-ref="selectUser" class="w-full">
                                       <option value="">-- Pilih Peminjam --</option>
                                        @foreach($usersList as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('form.user_id') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 dark:text-gray-300">Tanggal & Waktu Mulai</label>
                                <input type="datetime-local" wire:model="form.tanggal_mulai" class="w-full px-4 py-2 mt-1 bg-white border-none outline-none dark:bg-gray-800 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white" required>
                                @error('form.tanggal_mulai') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 dark:text-gray-300">Tanggal & Waktu Selesai</label>
                                <input type="datetime-local" wire:model="form.tanggal_selesai" class="w-full px-4 py-2 mt-1 bg-white border-none outline-none dark:bg-gray-800 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white" required>
                                @error('form.tanggal_selesai') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 dark:text-gray-300">Tujuan / Keperluan</label>
                                <input type="text" wire:model="form.tujuan" placeholder="Contoh: Rapat Koordinasi Tim" class="w-full px-4 py-2 mt-1 bg-white border-none outline-none dark:bg-gray-800 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white" required>
                                @error('form.tujuan') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- DETAIL 1: DAFTAR RUANGAN --}}
                        <div class="p-5 border border-gray-100 rounded-2xl dark:border-gray-800">
                            <div class="flex items-center justify-between mb-3">
                                <h5 class="font-bold text-gray-900 dark:text-white"><i class="mr-1 ti ti-building"></i> Ruangan yang Dipinjam</h5>
                                <button type="button" wire:click="addRoomRow" class="px-3 py-1.5 text-xs font-bold text-indigo-600 bg-indigo-50 dark:bg-indigo-500/10 rounded-lg hover:bg-indigo-100 transition-colors">
                                    + Tambah Ruangan
                                </button>
                            </div>

                            <div class="space-y-3">
                                @foreach($form['rooms'] as $index => $room)
                                    <div class="flex items-center gap-3 p-3 bg-white border border-gray-100 dark:bg-gray-900 rounded-xl dark:border-gray-800" wire:key="room-row-{{ $index }}">
                                        <div class="flex-1">
                                            <!-- Perhatikan penambahan class="relative w-full" -->
                                            <div wire:ignore wire:key="select-item-wrapper-{{ $index }}" class="relative w-full" x-data="{
                                                init() {
                                                    this.$nextTick(() => {
                                                        let el = $(this.$refs.selectItem).select2({
                                                            placeholder: '-- Pilih Barang --',
                                                            width: '100%',
                                                            dropdownParent: $(this.$el)
                                                        });
                                                        el.on('change', (e) => { $wire.set('form.rooms.0.room_id', e.target.value); }); $watch('$wire.form.rooms[0].room_id', (val) => { el.val(val).trigger('change.select2'); });
                                                    });
                                                }
                                            }">
                                                <select x-ref="selectItem" class="w-full">
                                                    <option value="">-- Pilih Ruangan --</option>
                                                    @foreach($roomsList as $r)
                                                        <option value="{{ $r->id }}">{{ $r->nama_ruangan }} (Kode: {{ $r->kode_ruangan }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <button type="button" wire:click="removeRoomRow({{ $index }})" class="p-2 text-red-500 transition-colors bg-red-50 rounded-xl hover:bg-red-100 dark:bg-red-500/10 dark:hover:bg-red-500/20" @if(count($form['rooms']) <= 1) disabled @endif>
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- DETAIL 2: DAFTAR BARANG --}}
                        <div class="p-5 border border-gray-100 rounded-2xl dark:border-gray-800">
                            <div class="flex items-center justify-between mb-3">
                                <h5 class="font-bold text-gray-900 dark:text-white"><i class="mr-1 ti ti-box"></i> Barang / Inventaris yang Dipinjam</h5>
                                <button type="button" wire:click="addItemRow" class="px-3 py-1.5 text-xs font-bold text-indigo-600 bg-indigo-50 dark:bg-indigo-500/10 rounded-lg hover:bg-indigo-100 transition-colors">
                                    + Tambah Barang
                                </button>
                            </div>

                            <div class="space-y-3">
                                @foreach($form['items'] as $index => $item)
                                    <div class="flex flex-col gap-3 p-3 bg-white border border-gray-100 dark:bg-gray-900 rounded-xl dark:border-gray-800 md:flex-row md:items-center" wire:key="item-row-{{ $index }}">
                                        <div class="flex-1">
                                            <!-- Perhatikan penambahan class="relative w-full" -->
                                            <div wire:ignore wire:key="select-item-wrapper-{{ $index }}" class="relative w-full" x-data="{
                                                init() {
                                                    this.$nextTick(() => {
                                                        let el = $(this.$refs.selectItem).select2({
                                                            placeholder: '-- Pilih Barang --',
                                                            width: '100%',
                                                            dropdownParent: $(this.$el)
                                                        });
                                                        el.on('change', (e) => { $wire.set('form.items.{{ $index }}.item_id', e.target.value); });
                                                        $watch('$wire.form.items[{{ $index }}].item_id', (val) => { el.val(val).trigger('change.select2'); });
                                                    });
                                                }
                                            }">
                                                <select x-ref="selectItem" class="w-full">
                                                    <option value="">-- Pilih Barang --</option>
                                                    @foreach($itemsList as $it)
                                                        <option value="{{ $it->id }}">{{ $it->nama_barang }} (Stok: {{ $it->jumlah_total }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="w-full md:w-32">
                                            <input type="number" wire:model="form.items.{{ $index }}.jumlah" min="1" placeholder="Qty" class="w-full px-3 py-2 border-none outline-none bg-gray-50 dark:bg-gray-800 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white">
                                        </div>
                                        <button type="button" wire:click="removeItemRow({{ $index }})" class="p-2 text-red-500 transition-colors bg-red-50 rounded-xl hover:bg-red-100 dark:bg-red-500/10 dark:hover:bg-red-500/20" @if(count($form['items']) <= 1) disabled @endif>
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- FOOTER ACTION --}}
                        <div class="flex justify-end gap-3 pt-6 border-t border-gray-100 dark:border-gray-800">
                            <button type="button" wire:click="closeAddModal" class="px-6 py-2.5 bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 text-sm font-bold rounded-xl hover:bg-gray-200 transition-all">Batal</button>
                            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-all">
                                Simpan Booking
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </section>

    {{-- MODAL POPUP: DATA PEMINJAMAN & EDIT STATUS --}}
    <section x-data="{ open: @entangle('isDetailModalOpen') }">
        <template x-teleport="body">
            <div x-show="open" class="fixed inset-0 z-9999 flex items-center justify-center p-4" x-cloak>
                <div x-show="open" x-transition.opacity wire:click="closeModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
                
                <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0" class="relative w-full max-w-4xl overflow-y-auto bg-white shadow-2xl dark:bg-gray-900 rounded-3xl p-8 max-h-[90vh]">
                    
                    {{-- Modal Header --}}
                    <div class="flex items-center justify-between pb-4 mb-6 border-b border-gray-100 dark:border-gray-800">
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 dark:text-white">Jadwal & Data Peminjam</h4>
                            <p class="text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ $selectedResourceName }}</p>
                        </div>
                        <button wire:click="closeModal" class="text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-white">
                            <i class="text-xl fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    {{-- List Peminjam Table --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left border-collapse font-mono">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50 dark:bg-gray-800 dark:border-gray-800">
                                    <th class="p-3 font-bold text-gray-400 uppercase">Peminjam</th>
                                    <th class="p-3 font-bold text-gray-400 uppercase">Tujuan / Keperluan</th>
                                    <th class="p-3 font-bold text-gray-400 uppercase">Waktu Pinjam</th>
                                    <th class="p-3 font-bold text-center text-gray-400 uppercase">Status</th>
                                    <th class="p-3 font-bold text-center text-gray-400 uppercase">Aksi & Notif</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($activeBorrowings as $item)
                                    @php
                                        // Generator URL WA Direct
                                        $cleanPhone = preg_replace('/[^0-9]/', '', $item['no_wa']);
                                        if (str_starts_with($cleanPhone, '0')) {
                                            $cleanPhone = '62' . substr($cleanPhone, 1);
                                        }
                                        $waMessage = rawurlencode("Halo {$item['peminjam']}, mengenai pengajuan peminjaman {$selectedResourceName} [TRX: {$item['kode_transaksi']}] status saat ini: *{$item['status']}*.");
                                        $waUrl = "https://wa.me/{$cleanPhone}?text={$waMessage}";
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                        <td class="p-3">
                                            <div class="font-bold text-gray-900 dark:text-white">{{ $item['peminjam'] }}</div>
                                            <div class="text-[10px] text-gray-400">{{ $item['kode_transaksi'] }}</div>
                                        </td>
                                        <td class="p-3 text-gray-600 dark:text-gray-300">
                                            {{ $item['tujuan'] }}
                                        </td>
                                        <td class="p-3">
                                            <div class="text-gray-900 font-semibold dark:text-white">{{ $item['tanggal_mulai'] }}</div>
                                            <div class="text-[10px] text-gray-400">s/d {{ $item['tanggal_selesai'] }}</div>
                                        </td>
                                        <td class="p-3 text-center">
                                            <span class="px-2.5 py-1 text-[10px] font-bold rounded-full uppercase 
                                                {{ $item['status'] === 'Disetujui' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                                {{ $item['status'] === 'Menunggu' ? 'bg-amber-100 text-amber-700' : '' }}
                                                {{ $item['status'] === 'Ditolak' ? 'bg-red-100 text-red-700' : '' }}
                                                {{ $item['status'] === 'Dipinjam' ? 'bg-blue-100 text-blue-700' : '' }}
                                                {{ $item['status'] === 'Dikembalikan' ? 'bg-gray-100 text-gray-700' : '' }}">
                                                {{ $item['status'] }}
                                            </span>
                                        </td>
                                        <td class="p-3">
                                            <div class="flex items-center justify-center gap-2">
                                                {{-- Quick Approve/Reject Action Dropdown / Buttons --}}
                                                @if ($item['status'] === 'Menunggu')
                                                    <button wire:click="updateStatus({{ $item['id'] }}, 'Disetujui')" title="Setujui" class="p-1.5 text-white bg-emerald-500 rounded-lg hover:bg-emerald-600 transition-colors">
                                                        <i class="fa-solid fa-check"></i>
                                                    </button>
                                                    <button wire:click="updateStatus({{ $item['id'] }}, 'Ditolak')" title="Tolak" class="p-1.5 text-white bg-red-500 rounded-lg hover:bg-red-600 transition-colors">
                                                        <i class="fa-solid fa-xmark"></i>
                                                    </button>
                                                @elseif ($item['status'] === 'Disetujui')
                                                    <button wire:click="updateStatus({{ $item['id'] }}, 'Dipinjam')" title="Tandai Sedang Dipinjam" class="px-2 py-1 text-[10px] font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                                                        Dipinjam
                                                    </button>
                                                @endif

                                                {{-- Tombol Notifikasi WhatsApp --}}
                                                @if (!empty($item['no_wa']))
                                                    <a href="{{ $waUrl }}" target="_blank" title="Kirim Notifikasi WA" class="p-1.5 text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors">
                                                        <i class="fa-brands fa-whatsapp text-sm"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-8 font-medium text-center text-gray-400">Tidak ada agenda peminjaman aktif pada rentang tanggal ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end pt-4 mt-6 border-t border-gray-100 dark:border-gray-800">
                        <x-button type="button" wire:click="closeModal" variant="secondary">Tutup</x-button>
                    </div>
                </div>
            </div>
        </template>
    </section>

    <x-toast />
</div>