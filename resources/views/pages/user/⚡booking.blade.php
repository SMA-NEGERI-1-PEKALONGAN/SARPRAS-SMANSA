<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Room;
use App\Models\Item;
use App\Models\Borrowing;
use App\Models\BorrowingDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

new #[Layout('layouts.user')] #[Title('Eksplorasi Peminjaman')] class extends Component
{
    use WithPagination, WithFileUploads;

    public string $type = 'ruangan';
    public string $dateFilter;
    public string $search = '';
    public string $category = 'all';

    public bool $isBookingModalOpen = false;
    public bool $isAlertModalOpen = false;
    public bool $isInfoModalOpen = false;
    public bool $isLoginAlertOpen = false;
    public bool $isErrorAlertOpen = false;

    public array $selectedItem = [];
    public array $activeBorrowings = [];
    public ?string $selectedResourceType = null;
    public ?int $selectedResourceId = null;
    public string $lastBookingName = '';
    public string $errorMessage = 'Terjadi kesalahan saat memproses pengajuan.';

    public $file_lampiran = null;
    public string $additionalResourceType = 'ruangan';
    public $additionalResourceId = '';

    public array $form = [
        'waktu_mulai' => '',
        'waktu_selesai' => '',
        'tujuan' => '',
        'catatan' => '',
        'rooms' => [],
        'items' => [],
    ];

    public function mount(): void
    {
        $this->dateFilter = now()->format('Y-m-d');
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingCategory(): void { $this->resetPage(); }
    public function updatingType(): void
    {
        $this->category = 'all';
        $this->search = '';
        $this->resetPage();
    }
    public function updatingDateFilter(): void
    {
        $this->resetPage();

        if ($this->isInfoModalOpen) {
            $this->loadBorrowingData();
        }
    }

    public function requestBooking(string $type, int $id): void
    {
        if (!auth()->check()) {
            $this->isLoginAlertOpen = true;
            return;
        }

        $resource = $this->getResourceById($type, $id);

        if (empty($resource)) {
            $this->addError('booking', 'Data fasilitas tidak ditemukan.');
            return;
        }

        if (!$resource['can_borrow']) {
            $this->addError('booking', 'Fasilitas tersebut sedang tidak dapat dipinjam.');
            return;
        }

        $this->resetValidation();
        $this->resetBookingForm();
        $this->addResourceToForm($resource);
        $this->isBookingModalOpen = true;
    }

    public function confirmLoginRedirect(): mixed
    {
        $this->isLoginAlertOpen = false;
        return redirect()->guest(route('login'));
    }

    public function closeLoginAlert(): void
    {
        $this->isLoginAlertOpen = false;
    }

    protected function resetBookingForm(): void
    {
        $this->form = [
            'waktu_mulai' => '',
            'waktu_selesai' => '',
            'tujuan' => '',
            'catatan' => '',
            'rooms' => [],
            'items' => [],
        ];
        $this->file_lampiran = null;
        $this->selectedItem = [];
        $this->additionalResourceType = 'ruangan';
        $this->additionalResourceId = '';
    }

    protected function addResourceToForm(array $resource): void
    {
        $this->selectedItem = $resource;

        if ($resource['tipe'] === 'ruangan') {
            if (!collect($this->form['rooms'])->contains('room_id', $resource['id'])) {
                $this->form['rooms'][] = [
                    'room_id' => $resource['id'],
                    'nama' => $resource['nama'],
                    'kode' => $resource['kode'],
                ];
            }
        } else {
            if (!collect($this->form['items'])->contains('item_id', $resource['id'])) {
                $this->form['items'][] = [
                    'item_id' => $resource['id'],
                    'jumlah' => 1,
                    'nama' => $resource['nama'],
                    'kode' => $resource['kode'],
                    'stok' => $resource['kapasitas'],
                ];
            }
        }
    }

    public function addAnotherResourceFromModal(): void
    {
        if (!auth()->check()) {
            $this->isLoginAlertOpen = true;
            return;
        }

        if (empty($this->additionalResourceId)) {
            $this->addError('additionalResourceId', 'Pilih fasilitas terlebih dahulu.');
            return;
        }

        $resource = $this->getResourceById($this->additionalResourceType, (int) $this->additionalResourceId);

        if (!$resource || !$resource['can_borrow']) {
            $this->addError('additionalResourceId', 'Fasilitas tidak tersedia untuk dipinjam.');
            return;
        }

        $alreadyExists = $resource['tipe'] === 'ruangan'
            ? collect($this->form['rooms'])->contains('room_id', $resource['id'])
            : collect($this->form['items'])->contains('item_id', $resource['id']);

        if ($alreadyExists) {
            $this->addError('additionalResourceId', 'Fasilitas tersebut sudah ada dalam daftar.');
            return;
        }

        $this->addResourceToForm($resource);
        $this->additionalResourceId = '';
        $this->resetErrorBag('additionalResourceId');
    }

    public function addAnotherResource(string $type, int $id): void
    {
        if (!auth()->check()) {
            $this->isLoginAlertOpen = true;
            return;
        }

        $resource = $this->getResourceById($type, $id);

        if (!$resource || !$resource['can_borrow']) {
            $this->addError('booking', 'Fasilitas tidak dapat ditambahkan.');
            return;
        }

        $this->addResourceToForm($resource);
        $this->dispatch('toast', type: 'success', message: 'Fasilitas ditambahkan ke daftar peminjaman.');
    }

    public function removeRoom(int $index): void
    {
        unset($this->form['rooms'][$index]);
        $this->form['rooms'] = array_values($this->form['rooms']);
    }

    public function removeItem(int $index): void
    {
        unset($this->form['items'][$index]);
        $this->form['items'] = array_values($this->form['items']);
    }

    public function openItemInfo(string $type, int $id): void
    {
        $this->openInfoModal($type, $id);
    }

    public function closeBookingModal(): void
    {
        $this->isBookingModalOpen = false;
        $this->resetValidation();
    }

    public function openInfoModal(?string $type = null, ?int $id = null): void
    {
        $this->selectedResourceType = $type;
        $this->selectedResourceId = $id;
        $this->loadBorrowingData();
        $this->isInfoModalOpen = true;
    }

    public function closeInfoModal(): void
    {
        $this->isInfoModalOpen = false;
        $this->selectedResourceType = null;
        $this->selectedResourceId = null;
        $this->activeBorrowings = [];
    }

    public function submitBooking(): void
    {
        if (!auth()->check()) {
            $this->isLoginAlertOpen = true;
            return;
        }

        if (empty($this->form['rooms']) && empty($this->form['items'])) {
            $this->addError('booking', 'Tambahkan minimal satu ruangan atau barang.');
            return;
        }

        $this->validate([
            'form.waktu_mulai' => ['required', 'date_format:H:i'],
            'form.waktu_selesai' => ['required', 'date_format:H:i', 'after:form.waktu_mulai'],
            'form.tujuan' => ['required', 'string', 'max:1000'],
            'form.catatan' => ['nullable', 'string', 'max:1000'],
            'form.rooms.*.room_id' => ['required', 'integer', 'exists:rooms,id'],
            'form.items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'form.items.*.jumlah' => ['required', 'integer', 'min:1'],
            'file_lampiran' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:1024'],
        ], [
            'form.waktu_selesai.after' => 'Waktu selesai harus lebih besar dari waktu mulai.',
            'file_lampiran.max' => 'Ukuran lampiran maksimal 1 MB.',
            'file_lampiran.mimes' => 'Lampiran hanya boleh PDF atau gambar.',
        ]);

        $start = Carbon::parse($this->dateFilter . ' ' . $this->form['waktu_mulai']);
        $end = Carbon::parse($this->dateFilter . ' ' . $this->form['waktu_selesai']);
        $activeStatuses = ['Menunggu', 'Disetujui', 'Dipinjam'];

        try {
            $bookingName = collect($this->form['rooms'])->pluck('nama')
                ->merge(collect($this->form['items'])->pluck('nama'))
                ->filter()->implode(', ');

            DB::transaction(function () use ($start, $end, $activeStatuses) {
                $overlap = function () use ($start, $end, $activeStatuses) {
                    return BorrowingDetail::query()
                        ->whereHas('borrowing', function ($q) use ($start, $end, $activeStatuses) {
                            $q->whereIn('status', $activeStatuses)
                                ->where('tanggal_mulai', '<', $end)
                                ->where('tanggal_selesai', '>', $start);
                        });
                };

                foreach ($this->form['rooms'] as $room) {
                    $exists = $overlap()->where('room_id', $room['room_id'])->lockForUpdate()->exists();
                    if ($exists) {
                        throw new \RuntimeException("Ruangan {$room['nama']} bentrok dengan jadwal peminjaman lain pada waktu tersebut.");
                    }
                }

                foreach ($this->form['items'] as $item) {
                    $stock = (int) Item::whereKey($item['item_id'])->value('jumlah_total');
                    $used = (int) $overlap()->where('item_id', $item['item_id'])->lockForUpdate()->sum('jumlah');
                    $requested = (int) $item['jumlah'];
                    $available = max(0, $stock - $used);

                    if ($requested > $available) {
                        throw new \RuntimeException("Stok {$item['nama']} tidak mencukupi. Tersedia {$available} unit pada waktu tersebut.");
                    }
                }

                $kode = $this->generateTransactionCode();

                $data = [
                    'kode_transaksi' => $kode,
                    'user_id' => auth()->id(),
                    'approved_by' => null,
                    'tujuan' => $this->form['tujuan'],
                    'tanggal_mulai' => $start,
                    'tanggal_selesai' => $end,
                    'status' => 'Menunggu',
                ];

                $borrowing = Borrowing::create($data);

                foreach ($this->form['rooms'] as $room) {
                    BorrowingDetail::create([
                        'borrowing_id' => $borrowing->id,
                        'room_id' => $room['room_id'],
                        'item_id' => null,
                        'jumlah' => 1,
                        'status' => 'Menunggu',
                    ]);
                }

                foreach ($this->form['items'] as $item) {
                    BorrowingDetail::create([
                        'borrowing_id' => $borrowing->id,
                        'room_id' => null,
                        'item_id' => $item['item_id'],
                        'jumlah' => (int) $item['jumlah'],
                        'status' => 'Menunggu',
                    ]);
                }

                // Lampiran bersifat opsional. Simpan hanya jika field DB tersedia.
                if ($this->file_lampiran && Schema::hasColumn('borrowings', 'file_lampiran')) {
                    $path = $this->file_lampiran->store('lampiran-peminjaman', 'public');
                    $borrowing->file_lampiran = $path;
                    $borrowing->save();
                }
            });

            $this->lastBookingName = $bookingName ?: 'Fasilitas';
            $this->closeBookingModal();
            $this->isAlertModalOpen = true;
            $this->dispatch('toast', type: 'success', message: 'Peminjaman berhasil diajukan dan menunggu persetujuan.');
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Terjadi kesalahan saat menyimpan pengajuan. Silakan coba kembali.';
            $this->isErrorAlertOpen = true;
            $this->addError('booking', $this->errorMessage);
        }
    }

    protected function generateTransactionCode(): string
    {
        do {
            $code = 'TRX-' . now()->format('Ymd') . '-' . random_int(1000, 9999);
        } while (Borrowing::where('kode_transaksi', $code)->exists());

        return $code;
    }

    protected function getResourceById(string $type, int $id): array
    {
        if ($type === 'ruangan') {
            $resource = Room::find($id);
            return $resource ? $this->mapRoom($resource) : [];
        }

        $resource = Item::find($id);
        return $resource ? $this->mapItem($resource) : [];
    }

    protected function mapRoom(Room $room): array
    {
        return [
            'id' => (int) $room->id,
            'tipe' => 'ruangan',
            'kategori' => $room->getAttribute('kategori') ?? 'ruangan',
            'kategori_label' => $room->getAttribute('kategori_label') ?? $room->getAttribute('kategori') ?? 'Ruangan',
            'nama' => $room->nama_ruangan,
            'kode' => $room->kode_ruangan,
            'deskripsi' => $room->getAttribute('deskripsi') ?? 'Fasilitas ruangan yang tersedia untuk peminjaman.',
            'kapasitas' => (int) ($room->kapasitas ?? 0),
            'satuan' => 'Orang',
            'icon' => $room->icon ?: 'fa-solid fa-door-closed',
            'fasilitas' => $this->parseFacilities($room->getAttribute('fasilitas') ?? $room->getAttribute('facility')),
            'can_borrow' => (bool) $room->status_tersedia,
        ];
    }

    protected function mapItem(Item $item): array
    {
        return [
            'id' => (int) $item->id,
            'tipe' => 'barang',
            'kategori' => $item->getAttribute('kategori') ?? 'barang',
            'kategori_label' => $item->getAttribute('kategori_label') ?? $item->getAttribute('kategori') ?? 'Barang / Inventaris',
            'nama' => $item->nama_barang,
            'kode' => $item->kode_barang,
            'deskripsi' => $item->getAttribute('deskripsi') ?? 'Barang inventaris yang dapat dipinjam.',
            'kapasitas' => (int) ($item->jumlah_total ?? 0),
            'satuan' => 'Unit',
            'icon' => $item->icon ?: 'fa-solid fa-box',
            'fasilitas' => [],
            'can_borrow' => (bool) $item->bisa_dipinjam,
        ];
    }

    protected function parseFacilities(?string $value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->toArray();
    }

    public function facilityIcon(string $facility): string
    {
        $facility = strtolower($facility);

        return match (true) {
            str_contains($facility, 'ac') || str_contains($facility, 'pendingin') => 'fa-snowflake',
            str_contains($facility, 'proyektor') || str_contains($facility, 'projector') => 'fa-video',
            str_contains($facility, 'internet') || str_contains($facility, 'wifi') => 'fa-wifi',
            str_contains($facility, 'tv') || str_contains($facility, 'smart') => 'fa-tv',
            str_contains($facility, 'sound') || str_contains($facility, 'speaker') || str_contains($facility, 'audio') => 'fa-volume-high',
            str_contains($facility, 'komputer') || str_contains($facility, 'pc') || str_contains($facility, 'laptop') => 'fa-computer',
            str_contains($facility, 'kabel') || str_contains($facility, 'listrik') || str_contains($facility, 'plug') => 'fa-plug',
            str_contains($facility, 'Headset') || str_contains($facility, 'headset') || str_contains($facility, 'earphone') => 'fa-headset',
            str_contains($facility, 'kamera') || str_contains($facility, 'camera') || str_contains($facility, 'video') => 'fa-camera',
            str_contains($facility, 'meja') => 'fa-table',
            str_contains($facility, 'kursi') => 'fa-chair',
            default => 'fa-circle-check',
        };
    }

    public function loadBorrowingData(): void
    {
        $startDate = Carbon::parse($this->dateFilter)->startOfDay();
        $endDate = Carbon::parse($this->dateFilter)->endOfDay();
        $activeStatuses = ['Menunggu', 'Disetujui', 'Dipinjam'];

        $query = BorrowingDetail::with(['borrowing.user', 'room', 'item'])
            ->whereHas('borrowing', function ($q) use ($startDate, $endDate, $activeStatuses) {
                $q->whereIn('status', $activeStatuses)
                    ->where('tanggal_mulai', '<', $endDate)
                    ->where('tanggal_selesai', '>', $startDate);
            });

        if ($this->selectedResourceId !== null && $this->selectedResourceType !== null) {
            $query->where($this->selectedResourceType === 'ruangan' ? 'room_id' : 'item_id', $this->selectedResourceId);
        }

        $this->activeBorrowings = $query->orderBy('id')->get()->map(function (BorrowingDetail $detail) {
            return [
                'id' => $detail->id,
                'kode_transaksi' => $detail->borrowing?->kode_transaksi ?? '-',
                'peminjam' => $detail->borrowing?->user?->name ?? '-',
                'tujuan' => $detail->borrowing?->tujuan ?? '-',
                'item' => $detail->room?->nama_ruangan ?? $detail->item?->nama_barang ?? '-',
                'tanggal_mulai' => optional($detail->borrowing?->tanggal_mulai)->format('d M Y H:i'),
                'tanggal_selesai' => optional($detail->borrowing?->tanggal_selesai)->format('d M Y H:i'),
                'jumlah' => $detail->jumlah,
                'status' => $detail->status,
            ];
        })->toArray();
    }

    public function closeAlertModal(): void
    {
        $this->isAlertModalOpen = false;
    }

    public function closeErrorAlert(): void
    {
        $this->isErrorAlertOpen = false;
    }

    public function with(): array
    {
        $baseQuery = $this->type === 'ruangan' ? Room::query() : Item::query();

        if ($this->search) {
            $baseQuery->where(function ($q) {
                if ($this->type === 'ruangan') {
                    $q->where('nama_ruangan', 'like', "%{$this->search}%")
                        ->orWhere('kode_ruangan', 'like', "%{$this->search}%");
                } else {
                    $q->where('nama_barang', 'like', "%{$this->search}%")
                        ->orWhere('kode_barang', 'like', "%{$this->search}%");
                }
            });
        }

        if ($this->category !== 'all' && Schema::hasColumn($this->type === 'ruangan' ? 'rooms' : 'items', 'kategori')) {
            $baseQuery->where('kategori', $this->category);
        }

        $categoryColumn = $this->type === 'ruangan' ? 'kategori' : 'kategori';
        $hasCategoryColumn = Schema::hasColumn($this->type === 'ruangan' ? 'rooms' : 'items', $categoryColumn);

        $categories = $hasCategoryColumn
            ? (clone $baseQuery)->whereNotNull($categoryColumn)->distinct()->orderBy($categoryColumn)->pluck($categoryColumn)
            : collect();

        $resources = $baseQuery
            ->orderBy($this->type === 'ruangan' ? 'nama_ruangan' : 'nama_barang')
            ->paginate(8);

        $startDate = Carbon::parse($this->dateFilter)->startOfDay();
        $endDate = Carbon::parse($this->dateFilter)->endOfDay();
        $activeStatuses = ['Menunggu', 'Disetujui', 'Dipinjam'];

        $resources->through(function ($resource) use ($startDate, $endDate, $activeStatuses) {
            $item = $this->type === 'ruangan' ? $this->mapRoom($resource) : $this->mapItem($resource);

            $detailQuery = BorrowingDetail::query()
                ->whereHas('borrowing', function ($q) use ($startDate, $endDate, $activeStatuses) {
                    $q->whereIn('status', $activeStatuses)
                        ->where('tanggal_mulai', '<', $endDate)
                        ->where('tanggal_selesai', '>', $startDate);
                });

            if ($this->type === 'ruangan') {
                $count = (clone $detailQuery)->where('room_id', $resource->id)->count();
                $item['booked_count'] = $count;
                $item['status_label'] = $count > 0 ? 'Ada Jadwal' : 'Tersedia';
            } else {
                $used = (int) (clone $detailQuery)->where('item_id', $resource->id)->sum('jumlah');
                $item['booked_count'] = $used;
                $item['available_qty'] = max(0, $item['kapasitas'] - $used);
                $item['status_label'] = $item['available_qty'] > 0 ? 'Tersedia' : 'Stok Habis';
            }

            return $item;
        });

        $bookingRoomsList = Room::where('status_tersedia', true)->orderBy('nama_ruangan')->get(['id', 'nama_ruangan', 'kode_ruangan']);
        $bookingItemsList = Item::where('bisa_dipinjam', true)->orderBy('nama_barang')->get(['id', 'nama_barang', 'kode_barang', 'jumlah_total']);

        return [
            'resources' => $resources,
            'categories' => $categories,
            'bookingRoomsList' => $bookingRoomsList,
            'bookingItemsList' => $bookingItemsList,
        ];
    }
};
?>

<div class="mt-4 flex-1 w-full max-w-7xl px-4 py-8 mx-auto sm:px-6 lg:px-8" x-data="{ alertOpen: @entangle('isAlertModalOpen'), bookingOpen: @entangle('isBookingModalOpen'), infoOpen: @entangle('isInfoModalOpen'), loginOpen: @entangle('isLoginAlertOpen'), errorOpen: @entangle('isErrorAlertOpen') }">
    <br>
    <div class="flex flex-col gap-6 mb-8 xl:flex-row xl:items-end xl:justify-between mt-4">
        <div class="mt-2">
            <h1 class="mb-2 text-2xl font-bold sm:text-3xl text-slate-900 dark:text-white">Eksplorasi Peminjaman</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Cari fasilitas, periksa jadwal, dan ajukan peminjaman.</p>
        </div>

        <div class="flex flex-wrap items-center w-full gap-3 xl:w-auto">
            <div class="relative w-full sm:w-auto">
                <select wire:model.live="type" class="w-full py-2.5 pl-10 pr-8 appearance-none bg-brand-50 border border-brand-200 text-brand-700 dark:bg-brand-900/30 dark:border-brand-800 dark:text-brand-300 rounded-xl text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer shadow-sm sm:w-48">
                    <option value="ruangan">Ruangan</option>
                    <option value="barang">Barang</option>
                </select>
                <i class="absolute text-sm -translate-y-1/2 pointer-events-none left-3 top-1/2 fa-solid fa-layer-group text-brand-500"></i>
            </div>

            <div class="relative w-full sm:w-44">
                <i class="absolute text-sm -translate-y-1/2 pointer-events-none left-3 top-1/2 fa-regular fa-calendar text-slate-400"></i>
                <input type="date" wire:model.live="dateFilter" class="w-full py-2.5 pl-9 pr-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm md:w-full">
            </div>

            <div class="relative flex-1 w-full sm:w-64">
                <i class="absolute text-sm -translate-y-1/2 pointer-events-none left-3 top-1/2 fa-solid fa-magnifying-glass text-slate-400"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari {{ $type === 'ruangan' ? 'nama / kode ruangan' : 'nama / kode barang' }}..." class="w-full py-2.5 pl-9 pr-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm">
            </div>

            <button wire:click="openInfoModal" class="flex items-center justify-center w-full gap-2 px-4 py-2.5 text-sm font-semibold text-white transition-colors bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 rounded-xl shadow-sm sm:w-auto">
                <i class="fa-solid fa-circle-info text-brand-400"></i> Info Jadwal
            </button>
        </div>
    </div>

    @error('booking')
        <div class="p-3 mb-6 text-sm font-medium text-rose-700 border rounded-xl bg-rose-50 border-rose-200 dark:bg-rose-900/20 dark:text-rose-300 dark:border-rose-800">{{ $message }}</div>
    @enderror

    @if($categories->count())
        <div class="mb-6 overflow-x-auto border-b border-slate-200 dark:border-slate-700/80">
            <ul class="flex gap-6 text-sm font-medium min-w-max">
                <li><button wire:click="$set('category', 'all')" class="{{ $category === 'all' ? 'pb-4 border-b-2 border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'pb-4 border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400' }} whitespace-nowrap">Semua</button></li>
                @foreach($categories as $cat)
                    <li><button wire:click="$set('category', '{{ $cat }}')" class="{{ $category === $cat ? 'pb-4 border-b-2 border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'pb-4 border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400' }} whitespace-nowrap">{{ str($cat)->replace('_', ' ')->title() }}</button></li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($resources as $item)
            <div wire:key="resource-{{ $item['tipe'] }}-{{ $item['id'] }}" class="flex flex-col overflow-hidden transition-all duration-300 bg-white border border-slate-200 dark:bg-slate-800 rounded-2xl dark:border-slate-700 hover:shadow-xl hover:shadow-brand-500/5">
                <div class="relative flex items-center justify-center h-36 overflow-hidden bg-slate-100 dark:bg-slate-700/50">
                    <i class="text-5xl transition-transform duration-500 fa-solid {{ str_replace('fa-solid ', '', $item['icon']) }} text-slate-300 dark:text-slate-600 group-hover:scale-110"></i>
                    <div class="absolute top-3 right-3 px-2.5 py-1 rounded-lg text-[10px] font-bold {{ $item['status_label'] === 'Tersedia' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' }}">
                        {{ $item['status_label'] }}
                    </div>
                </div>

                <div class="flex flex-col flex-1 p-5">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400">{{ $item['kategori_label'] }}</span>
                        <span class="text-[10px] font-bold text-brand-600 dark:text-brand-400">#{{ $item['kode'] }}</span>
                    </div>
                    <h3 class="mb-3 text-lg font-bold text-slate-900 dark:text-white">{{ $item['nama'] }}</h3>
                    <p class="mb-4 text-xs text-slate-500 dark:text-slate-400 line-clamp-2">{{ $item['deskripsi'] }}</p>

                    <div class="flex items-center gap-2 mb-4 text-xs text-slate-600 dark:text-slate-300">
                        <span class="px-2 py-1 font-medium border rounded-md bg-slate-50 dark:bg-slate-900/50 border-slate-100 dark:border-slate-700 whitespace-nowrap">
                            <i class="fa-solid {{ $item['tipe'] === 'ruangan' ? 'fa-users' : 'fa-box' }} text-slate-400 mr-1"></i>
                            {{ $item['tipe'] === 'ruangan' ? $item['kapasitas'].' Orang' : $item['kapasitas'].' Unit' }}
                        </span>
                    </div>

                    @if($item['tipe'] === 'ruangan')
                        <div
                            class="flex gap-2 pb-1 mb-5 overflow-x-auto hide-scrollbar whitespace-nowrap snap-x"
                            aria-label="Daftar fasilitas ruangan"
                        >
                            @forelse($item['fasilitas'] as $facility)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[10px] font-semibold text-slate-600 bg-slate-100 border border-slate-200 rounded-lg dark:bg-slate-700/70 dark:text-slate-300 dark:border-slate-600 shrink-0">
                                    <i class="fa-solid {{ $this->facilityIcon($facility) }} text-brand-500"></i>
                                    <span>{{ $facility }}</span>
                                </span>
                            @empty
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[10px] font-medium text-slate-400 bg-slate-50 border border-slate-200 rounded-lg dark:bg-slate-900/40 dark:border-slate-700 shrink-0">
                                    <i class="fa-solid fa-minus-circle"></i>
                                    Tidak ada fasilitas
                                </span>
                            @endforelse
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-2 mt-auto">
                        <button wire:click="openInfoModal('{{ $item['tipe'] }}', {{ $item['id'] }})" class="flex items-center justify-center gap-2 py-2.5 text-xs font-semibold rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600">
                            <i class="fa-solid fa-circle-info"></i> Info
                        </button>
                        @if($item['can_borrow'])
                            <button wire:click="requestBooking('{{ $item['tipe'] }}', {{ $item['id'] }})" class="py-2.5 text-xs font-semibold text-white rounded-xl bg-brand-600 hover:bg-brand-700 shadow-lg shadow-brand-500/20">
                                Pinjam
                            </button>
                        @else
                            <button disabled class="py-2.5 text-xs font-semibold rounded-xl text-slate-400 bg-slate-100 dark:bg-slate-700 cursor-not-allowed">Tidak Tersedia</button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 text-center bg-white border border-slate-200 shadow-sm rounded-2xl dark:bg-slate-800 dark:border-slate-700">
                <i class="mb-3 text-4xl text-slate-300 fa-solid fa-box-open dark:text-slate-600"></i>
                <p class="text-sm font-semibold text-slate-400">Data tidak ditemukan.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $resources->links() }}
    </div>

    {{-- Modal Login Guard --}}
    <template x-teleport="body">
        <div x-show="loginOpen" class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" x-cloak>
            <div class="w-full max-w-sm p-6 text-center bg-white shadow-2xl dark:bg-slate-800 rounded-2xl">
                <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 text-2xl rounded-full bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400"><i class="fa-solid fa-lock"></i></div>
                <h3 class="mb-2 text-lg font-bold text-slate-900 dark:text-white">Login Diperlukan</h3>
                <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Silakan login terlebih dahulu untuk melakukan peminjaman fasilitas.</p>
                <div class="flex gap-3">
                    <button wire:click="closeLoginAlert" class="flex-1 py-2.5 text-sm font-semibold rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300">Batal</button>
                    <button wire:click="confirmLoginRedirect" class="flex-1 py-2.5 text-sm font-semibold text-white rounded-xl bg-brand-600 hover:bg-brand-700">OK, Login</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Modal Info Jadwal --}}
    <template x-teleport="body">
        <div x-show="infoOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" x-cloak>
            <div class="absolute inset-0" wire:click="closeInfoModal"></div>
            <div x-show="infoOpen" x-transition class="relative w-full max-w-5xl max-h-[90vh] flex flex-col bg-white dark:bg-slate-800 rounded-2xl shadow-2xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                    <div>
                        <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900 dark:text-white"><i class="fa-solid fa-calendar-day text-brand-500"></i> Jadwal Peminjaman</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ Carbon::parse($dateFilter)->translatedFormat('l, d F Y') }} @if($selectedResourceId) • {{ $selectedResourceType === 'ruangan' ? 'Ruangan' : 'Barang' }} @endif</p>
                    </div>
                    <button wire:click="closeInfoModal" class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-500 hover:text-rose-500"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="p-6 overflow-y-auto">
                    <div class="overflow-hidden border rounded-xl border-slate-200 dark:border-slate-700">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-slate-600 dark:text-slate-300">
                                <thead class="text-xs font-semibold uppercase border-b bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700 text-slate-500"><tr><th class="px-4 py-3">Waktu</th><th class="px-4 py-3">Peminjam</th><th class="px-4 py-3">Barang / Ruangan</th><th class="px-4 py-3">Tujuan</th><th class="px-4 py-3 text-center">Status</th></tr></thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                @forelse($activeBorrowings as $schedule)
                                    <tr><td class="px-4 py-3 font-semibold whitespace-nowrap text-slate-800 dark:text-slate-200">{{ $schedule['tanggal_mulai'] }}<br><span class="text-[10px] font-normal text-slate-400">s/d {{ $schedule['tanggal_selesai'] }}</span></td><td class="px-4 py-3"><div class="font-medium">{{ $schedule['peminjam'] }}</div><div class="text-[10px] text-slate-400">{{ $schedule['kode_transaksi'] }}</div></td><td class="px-4 py-3">{{ $schedule['item'] }}</td><td class="px-4 py-3">{{ $schedule['tujuan'] }}</td><td class="px-4 py-3 text-center"><span class="px-2.5 py-1 rounded-md text-[11px] font-bold {{ $schedule['status'] === 'Menunggu' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' : ($schedule['status'] === 'Disetujui' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400') }}">{{ $schedule['status'] }}</span></td></tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Belum ada jadwal peminjaman pada tanggal ini.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- Modal Booking Multi Item --}}
    <template x-teleport="body">
        <div x-show="bookingOpen" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" x-cloak>
            <div class="absolute inset-0 bg-transparent"></div>
            <div x-show="bookingOpen" x-transition class="relative w-full max-w-3xl max-h-[92vh] overflow-y-auto hide-scrollbar bg-white dark:bg-slate-800 rounded-2xl shadow-2xl">
                <div class="sticky top-0 z-20 flex items-center justify-between px-6 py-4 border-b bg-white/95 dark:bg-slate-800/95 backdrop-blur-md border-slate-100 dark:border-slate-700">
                    <div><h2 class="text-lg font-bold text-slate-900 dark:text-white">Form Peminjaman</h2><p class="text-xs text-slate-500 dark:text-slate-400">{{ Carbon::parse($dateFilter)->translatedFormat('l, d F Y') }}</p></div>
                    <button wire:click="closeBookingModal" class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-500 hover:text-rose-500"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <form wire:submit="submitBooking" class="p-6 space-y-6">
                    @error('booking')<div class="p-3 text-xs font-medium border rounded-xl text-rose-700 bg-rose-50 border-rose-200 dark:bg-rose-900/20 dark:text-rose-300">{{ $message }}</div>@enderror

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div><label class="block mb-1.5 text-xs font-semibold">Waktu Mulai <span class="text-rose-500">*</span></label><input type="time" wire:model="form.waktu_mulai" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm" required>@error('form.waktu_mulai')<span class="text-[10px] text-rose-500">{{ $message }}</span>@enderror</div>
                        <div><label class="block mb-1.5 text-xs font-semibold">Waktu Selesai <span class="text-rose-500">*</span></label><input type="time" wire:model="form.waktu_selesai" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm" required>@error('form.waktu_selesai')<span class="text-[10px] text-rose-500">{{ $message }}</span>@enderror</div>
                    </div>

                    <div class="p-4 border rounded-2xl bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700">
                        <div class="flex flex-col gap-3 mb-4 sm:flex-row sm:items-end sm:justify-between">
                            <div><h3 class="text-sm font-bold text-slate-800 dark:text-white">Daftar Fasilitas</h3><span class="text-[10px] text-slate-400">{{ count($form['rooms']) + count($form['items']) }} item dipilih</span></div>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <select wire:model.live="additionalResourceType" class="px-3 py-2 text-xs font-semibold border rounded-lg bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700">
                                    <option value="ruangan">Ruangan</option>
                                    <option value="barang">Barang</option>
                                </select>
                                <div wire:key="facility-select-{{ $additionalResourceType }}" wire:ignore class="min-w-52" x-data x-init="$nextTick(() => { const el = $($refs.facilitySelect).select2({ placeholder: '+ Tambah fasilitas', allowClear: true, width: '100%' }); el.on('change', () => $wire.set('additionalResourceId', el.val() || '')); $watch('$wire.additionalResourceId', value => el.val(value).trigger('change.select2')); })">
                                    <select x-ref="facilitySelect" class="w-full px-3 py-2 text-xs border rounded-lg bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700">
                                        <option value=""></option>
                                        @if($additionalResourceType === 'ruangan')
                                            @foreach($bookingRoomsList as $r)
                                                <option value="{{ $r->id }}">{{ $r->nama_ruangan }} #{{ $r->kode_ruangan }}</option>
                                            @endforeach
                                        @else
                                            @foreach($bookingItemsList as $it)
                                                <option value="{{ $it->id }}">{{ $it->nama_barang }} #{{ $it->kode_barang }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                <button type="button" wire:click="addAnotherResourceFromModal" class="px-3 py-2 text-xs font-bold text-white rounded-lg bg-brand-600 hover:bg-brand-700"><i class="mr-1 fa-solid fa-plus"></i> Tambah</button>
                            </div>
                        </div>
                        @error('additionalResourceId')<span class="block mb-3 text-[10px] text-rose-500">{{ $message }}</span>@enderror

                        <div class="space-y-3 max-h-80 overflow-y-auto hide-scrollbar pr-1">
                            @forelse($form['rooms'] as $index => $room)
                                <div wire:key="booking-room-{{ $index }}" class="p-4 bg-white border rounded-xl dark:bg-slate-800 border-slate-200 dark:border-slate-700">
                                    <div class="flex items-start justify-between gap-3"><div><div class="text-[10px] font-bold uppercase text-brand-500"> #{{ $room['kode'] }}</div><div class="text-sm font-bold text-slate-800 dark:text-white">{{ $room['nama'] }}</div></div><div class="flex gap-2"><button type="button" wire:click="openItemInfo('ruangan', {{ $room['room_id'] }})" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-[11px] font-semibold text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"><i class="fa-solid fa-circle-info"></i> Info</button><button type="button" wire:click="removeRoom({{ $index }})" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-[11px] font-semibold text-rose-600 bg-rose-50 rounded-lg hover:bg-rose-100 dark:bg-rose-500/10"><i class="fa-solid fa-trash"></i> Hapus</button></div></div>
                                </div>
                            @empty
                            @endforelse

                            @foreach($form['items'] as $index => $item)
                                <div wire:key="booking-item-{{ $index }}" class="p-4 bg-white border rounded-xl dark:bg-slate-800 border-slate-200 dark:border-slate-700">
                                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"><div><div class="text-[10px] font-bold uppercase text-brand-500"> #{{ $item['kode'] }}</div><div class="text-sm font-bold text-slate-800 dark:text-white">{{ $item['nama'] }}</div><div class="text-[10px] text-slate-400">Stok {{ $item['stok'] }} unit</div></div><div class="flex items-center gap-2"><input type="number" wire:model="form.items.{{ $index }}.jumlah" min="1" max="{{ $item['stok'] }}" class="w-24 px-3 py-2 text-sm border rounded-lg bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700"><button type="button" wire:click="openItemInfo('barang', {{ $item['item_id'] }})" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-[11px] font-semibold text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"><i class="fa-solid fa-circle-info"></i> Info</button><button type="button" wire:click="removeItem({{ $index }})" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-[11px] font-semibold text-rose-600 bg-rose-50 rounded-lg hover:bg-rose-100 dark:bg-rose-500/10"><i class="fa-solid fa-trash"></i> Hapus</button></div></div>
                                    @error('form.items.'.$index.'.jumlah')<span class="text-[10px] text-rose-500">{{ $message }}</span>@enderror
                                </div>
                            @endforeach

                            @if(empty($form['rooms']) && empty($form['items']))
                                <div class="py-8 text-center border border-dashed rounded-xl border-slate-300 dark:border-slate-600">
                                    <i class="mb-2 text-xl fa-solid fa-list-check text-slate-400"></i>
                                    <p class="text-xs font-semibold text-slate-400">Belum ada fasilitas dipilih.</p>
                                </div>
                            @endif
                        </div>

                    </div>

                    <div><label class="block mb-1.5 text-xs font-semibold">Tujuan Kegiatan <span class="text-rose-500">*</span></label><textarea rows="3" wire:model="form.tujuan" placeholder="Contoh : Rapat" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm resize-none" required></textarea>@error('form.tujuan')<span class="text-[10px] text-rose-500">{{ $message }}</span>@enderror</div>
                    <div><label class="block mb-1.5 text-xs font-semibold">Catatan Tambahan</label><input type="text" placeholder="Contoh: Pemesanan di luar jam operasional" wire:model="form.catatan" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm">@error('form.catatan')<span class="text-[10px] text-rose-500">{{ $message }}</span>@enderror</div>

                    <div class="p-4 border rounded-2xl border-slate-200 dark:border-slate-700" x-data="{ progress: 0, uploading: false }" x-on:livewire-upload-start.window="uploading=true; progress=0" x-on:livewire-upload-progress.window="progress=$event.detail.progress" x-on:livewire-upload-finish.window="uploading=false; progress=100" x-on:livewire-upload-error.window="uploading=false">
                        <label class="block mb-2 text-xs font-semibold">Lampiran (File SP) (Opsional) <span class="text-slate-400 font-normal">(PDF/JPG/JPEG/PNG/WebP, maksimal 1 MB)</span></label>
                        <input type="file" wire:model="file_lampiran" accept=".pdf,.jpg,.jpeg,.png,.webp" class="w-full text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                        <div wire:loading wire:target="file_lampiran" class="flex items-center gap-2 mt-2 text-[10px] font-semibold text-brand-600"><i class="fa-solid fa-circle-notch animate-spin"></i> Menyiapkan file...</div>
                        @error('file_lampiran')<span class="block mt-1 text-[10px] text-rose-500">{{ $message }}</span>@enderror

                        <div x-show="uploading" class="mt-3">
                            <div class="flex justify-between mb-1 text-[10px] font-semibold text-slate-500"><span>Mengunggah lampiran...</span><span x-text="progress + '%'"></span></div>
                            <div class="w-full h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"><div class="h-full transition-all duration-150 bg-brand-500" :style="`width: ${progress}%`"></div></div>
                        </div>

                        @if($file_lampiran)
                            <div class="p-3 mt-3 border rounded-xl bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700">
                                <div class="mb-3 text-xs font-bold text-slate-700 dark:text-slate-200">Preview Lampiran</div>
                                @if(str_starts_with($file_lampiran->getMimeType(), 'image/'))
                                    <img src="{{ $file_lampiran->temporaryUrl() }}" class="object-contain w-full max-h-56 rounded-lg border border-slate-200 dark:border-slate-700" alt="Preview lampiran">
                                @elseif($file_lampiran->getMimeType() === 'application/pdf')
                                    <div class="flex items-center gap-3 p-4 text-xs font-medium text-slate-600 border border-dashed rounded-lg bg-white dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700">
                                        <i class="text-2xl fa-solid fa-file-pdf text-rose-500"></i>
                                        <div>PDF terpilih.</div>
                                    </div>
                                @endif
                                <div class="flex items-center gap-3 mt-3"><div class="min-w-0"><div class="text-xs font-bold text-slate-700 dark:text-slate-200 truncate">{{ $file_lampiran->getClientOriginalName() }}</div><div class="text-[10px] text-slate-400">{{ number_format($file_lampiran->getSize() / 1024, 0) }} KB</div></div></div>
                            </div>
                        @endif
                    </div>

                    <div class="flex gap-3 pt-2"><button type="button" wire:click="closeBookingModal" class="flex-1 py-3 text-sm font-medium rounded-xl text-slate-600 bg-slate-100 hover:bg-slate-200 dark:text-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600">Batal</button><button type="submit" class="flex-[2] py-3 rounded-xl font-semibold text-sm text-white bg-brand-600 hover:bg-brand-700 flex items-center justify-center gap-2"><span wire:loading.remove wire:target="submitBooking">Ajukan Peminjaman</span><span wire:loading wire:target="submitBooking"><i class="fa-solid fa-circle-notch animate-spin"></i> Memproses...</span></button></div>
                </form>
            </div>
        </div>
    </template>

    {{-- Modal Error Submit --}}
    <template x-teleport="body">
        <div x-show="errorOpen" class="fixed inset-0 z-[115] flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm" x-cloak>
            <div class="w-full max-w-sm p-6 text-center bg-white shadow-2xl dark:bg-slate-800 rounded-2xl">
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 text-3xl rounded-full bg-rose-100 dark:bg-rose-900/30 text-rose-500 dark:text-rose-400"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <h3 class="mb-2 text-lg font-bold text-slate-900 dark:text-white">Pengajuan Gagal</h3>
                <p class="mb-6 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $errorMessage }}</p>
                <button wire:click="closeErrorAlert" class="w-full py-2.5 rounded-xl font-semibold text-white bg-slate-800 hover:bg-slate-900 dark:bg-slate-600">Tutup</button>
            </div>
        </div>
    </template>

    {{-- Modal Sukses --}}
    <template x-teleport="body">
        <div x-show="alertOpen" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" x-cloak>
            <div class="w-full max-w-sm p-6 text-center bg-white shadow-2xl dark:bg-slate-800 rounded-2xl">
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 text-3xl rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-500 dark:text-emerald-400"><i class="fa-regular fa-circle-check"></i></div>
                <h3 class="mb-2 text-lg font-bold text-slate-900 dark:text-white">Pengajuan Berhasil!</h3>
                <p class="mb-6 text-sm leading-relaxed text-slate-600 dark:text-slate-400">Peminjaman <b>{{ $lastBookingName }}</b> berhasil diajukan dan menunggu persetujuan admin.</p>
                <button wire:click="closeAlertModal" class="w-full py-2.5 rounded-xl font-semibold text-white bg-slate-800 hover:bg-slate-900 dark:bg-slate-600">Tutup</button>
            </div>
        </div>
    </template>
</div>
