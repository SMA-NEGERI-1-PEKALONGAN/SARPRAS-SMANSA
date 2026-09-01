<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Room;
use App\Models\Item;
use App\Models\User;
use App\Models\Borrowing;
use App\Models\BorrowingDetail;
use App\Models\SystemNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

new #[Layout('layouts.user')] #[Title('Eksplorasi Peminjaman')] class extends Component
{
    use WithPagination, WithFileUploads;

    public string $type = 'ruangan';
    public string $dateFilter;
    public string $search = '';
    public string $filterTipe = 'all';

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

    public array $selectedFacilities = [];
    public bool $isTermsModalOpen = false;
    public bool $termsAgreed = false;
    public ?string $signatureData = null;

    public array $form = [
        'waktu_mulai' => '',
        'waktu_selesai' => '',
        'tujuan' => '',
        'catatan' => '',
        'rooms' => [],
        'items' => [],
    ];

    public array $roomTypes = [
        'Kelas',
        'Laboratorium',
        'Multimedia',
        'Aula',
        'UKS',
        'Perpustakaan',
        'Ruang Khusus',
        'Ruang Server',
        'Ruang Podcast',
        'Fasilitas Olahraga',
        'Lainnya',
    ];

    public function mount(): void
    {
        $this->dateFilter = now()->format('Y-m-d');
    }

    protected function sendBookingNotification(Borrowing $borrowing): void
    {
        $borrowing->loadMissing(['user', 'details.room', 'details.item']);

        $resourceNames = $borrowing->details
            ->map(fn ($detail) => $detail->room?->nama_ruangan ?? $detail->item?->nama_barang)
            ->filter()
            ->unique()
            ->values();

        $resourceName = $resourceNames->isNotEmpty()
            ? $resourceNames->implode(', ')
            : 'fasilitas';

        $userName = $borrowing->user?->name ?? 'Pengguna'; //

        $url = route('admin.booking');

        $admins = User::query()
            ->where(function ($query) {
                $query->where('role', 'admin');
            })
            ->get(['id']);

        foreach ($admins as $admin) {
            SystemNotification::create([
                'user_id' => $admin->id,
                'title' => 'Pengajuan Peminjaman Baru',
                'message' => "{$userName} mengajukan peminjaman {$resourceName}. Silakan periksa dan proses pengajuan tersebut.",
                'url' => $url,
                'is_read' => false,
            ]);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterTipe(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->filterTipe = 'all';
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
        $this->selectedFacilities = [];
        $this->isTermsModalOpen = false;
        $this->termsAgreed = false;
        $this->signatureData = null;
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
                    'icon' => $resource['icon'],
                    'fasilitas' => [],
                    'available_fasilitas' => $resource['fasilitas'] ?? [],
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

    protected function validateBookingTime(): void
    {
        $selectedDate = Carbon::parse($this->dateFilter)->startOfDay();
        $today = now()->startOfDay();

        if ($selectedDate->equalTo($today)) {
            $currentTime = now()->format('H:i');

            if (
                !$this->form['waktu_mulai'] ||
                $this->form['waktu_mulai'] < $currentTime
            ) {
                throw new \RuntimeException(
                    'Waktu mulai tidak boleh kurang dari waktu saat ini.'
                );
            }
        }
    }

    protected function validateScheduleConflict(): void
    {
        $start = Carbon::parse(
            $this->dateFilter . ' ' . $this->form['waktu_mulai']
        );

        $end = Carbon::parse(
            $this->dateFilter . ' ' . $this->form['waktu_selesai']
        );

        $activeStatuses = ['Menunggu', 'Disetujui', 'Dipinjam'];

        $overlap = fn () => BorrowingDetail::query()
            ->whereHas('borrowing', function ($q) use (
                $start,
                $end,
                $activeStatuses
            ) {
                $q->whereIn('status', $activeStatuses)
                    ->where('tanggal_mulai', '<', $end)
                    ->where('tanggal_selesai', '>', $start);
            });

        foreach ($this->form['rooms'] as $index => $room) {
            $exists = $overlap()
                ->where('room_id', $room['room_id'])
                ->exists();

            if ($exists) {
                $this->addError(
                    "form.rooms.{$index}",
                    "Ruangan {$room['nama']} sudah memiliki jadwal pada waktu tersebut."
                );

                throw new \RuntimeException(
                    "Ruangan {$room['nama']} bentrok dengan jadwal peminjaman lain."
                );
            }
        }

        foreach ($this->form['items'] as $index => $item) {
            $stock = (int) Item::whereKey(
                $item['item_id']
            )->value('jumlah_total');

            $used = (int) $overlap()
                ->where('item_id', $item['item_id'])
                ->sum('jumlah');

            $available = max(0, $stock - $used);

            if ((int) $item['jumlah'] > $available) {
                $this->addError(
                    "form.items.{$index}.jumlah",
                    "Stok {$item['nama']} tidak mencukupi. Tersedia {$available} unit pada waktu tersebut."
                );

                throw new \RuntimeException(
                    "Stok {$item['nama']} tidak mencukupi."
                );
            }
        }
    }

    public function toggleAllRoomFacilities($index)
    {
        $available = $this->form['rooms'][$index]['available_fasilitas'] ?? [];
        $selected = $this->form['rooms'][$index]['fasilitas'] ?? [];

        if (count($selected) === count($available)) {
            $this->form['rooms'][$index]['fasilitas'] = [];
        } else {
            $this->form['rooms'][$index]['fasilitas'] = $available;
        }
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
            'form.catatan' => ['required', 'string', 'max:1000'],
            'form.rooms.*.room_id' => ['required', 'integer', 'exists:rooms,id'],
            'form.rooms.*.fasilitas' => ['array'],
            'form.items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'form.items.*.jumlah' => ['required', 'integer', 'min:1'],
            'file_lampiran' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:1024'],
        ], [
            'form.waktu_selesai.after' => 'Waktu selesai harus lebih besar dari waktu mulai.',
            'file_lampiran.max' => 'Ukuran lampiran maksimal 1 MB.',
            'file_lampiran.mimes' => 'Lampiran hanya boleh PDF atau gambar.',
        ]);

        try {
            $this->validateBookingTime();
            $this->validateScheduleConflict();

            $this->resetValidation();
            $this->termsAgreed = false;
            $this->signatureData = null;
            $this->isTermsModalOpen = true;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->isErrorAlertOpen = true;
        }
    }

    public function confirmBooking(): void
    {
        if (!$this->termsAgreed) {
            $this->addError(
                'termsAgreed',
                'Anda harus menyetujui syarat dan ketentuan.'
            );
            return;
        }

        if (empty($this->signatureData)) {
            $this->addError(
                'signatureData',
                'Tanda tangan wajib diisi.'
            );
            return;
        }

        try {
            $start = Carbon::parse(
                $this->dateFilter . ' ' . $this->form['waktu_mulai']
            );

            $end = Carbon::parse(
                $this->dateFilter . ' ' . $this->form['waktu_selesai']
            );

            $activeStatuses = ['Menunggu', 'Disetujui', 'Dipinjam'];

            $bookingName = collect($this->form['rooms'])
                ->pluck('nama')
                ->merge(
                    collect($this->form['items'])->pluck('nama')
                )
                ->filter()
                ->unique()
                ->implode(', ');

            $borrowing = DB::transaction(function () use (
                $start,
                $end,
                $activeStatuses
            ) {
                $overlap = function () use (
                    $start,
                    $end,
                    $activeStatuses
                ) {
                    return BorrowingDetail::query()
                        ->whereHas('borrowing', function ($q) use (
                            $start,
                            $end,
                            $activeStatuses
                        ) {
                            $q->whereIn('status', $activeStatuses)
                                ->where('tanggal_mulai', '<', $end)
                                ->where('tanggal_selesai', '>', $start);
                        });
                };

                foreach ($this->form['rooms'] as $room) {
                    if (
                        $overlap()
                            ->where('room_id', $room['room_id'])
                            ->lockForUpdate()
                            ->exists()
                    ) {
                        throw new \RuntimeException(
                            "Ruangan {$room['nama']} sudah dipesan pada waktu tersebut."
                        );
                    }
                }

                foreach ($this->form['items'] as $item) {
                    $stock = (int) Item::whereKey(
                        $item['item_id']
                    )->value('jumlah_total');

                    $used = (int) $overlap()
                        ->where('item_id', $item['item_id'])
                        ->lockForUpdate()
                        ->sum('jumlah');

                    $available = max(0, $stock - $used);

                    if ((int) $item['jumlah'] > $available) {
                        throw new \RuntimeException(
                            "Stok {$item['nama']} tidak mencukupi. Tersedia {$available} unit."
                        );
                    }
                }

                $borrowing = Borrowing::create([
                    'kode_transaksi' => $this->generateTransactionCode(),
                    'user_id' => auth()->id(),
                    'approved_by' => null,
                    'tujuan' => $this->form['tujuan'],
                    'tanggal_mulai' => $start,
                    'tanggal_selesai' => $end,
                    'status' => 'Menunggu',
                    'tanda_tangan' => $this->signatureData,
                ]);

                foreach ($this->form['rooms'] as $room) {
                    BorrowingDetail::create([
                        'borrowing_id' => $borrowing->id,
                        'room_id' => $room['room_id'],
                        'item_id' => null,
                        'jumlah' => 1,
                        'status' => 'Menunggu',
                        'fasilitas' => json_encode(
                            $room['fasilitas'] ?? [],
                            JSON_UNESCAPED_UNICODE
                        ),
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

                if (
                    $this->file_lampiran &&
                    Schema::hasColumn('borrowings', 'file_lampiran')
                ) {
                    $borrowing->file_lampiran =
                        $this->file_lampiran->store(
                            'lampiran-peminjaman',
                            'public'
                        );

                    $borrowing->save();
                }

                return $borrowing;
            });

            $this->sendBookingNotification($borrowing);

            $this->lastBookingName = $bookingName ?: 'Fasilitas';

            $this->isTermsModalOpen = false;
            $this->closeBookingModal();
            $this->isAlertModalOpen = true;

            $this->dispatch(
                'toast',
                type: 'success',
                message: 'Peminjaman berhasil diajukan dan menunggu persetujuan.'
            );
        } catch (\Throwable $e) {
            report($e);

            $this->errorMessage = $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Terjadi kesalahan saat menyimpan pengajuan.';

            $this->isTermsModalOpen = false;
            $this->isErrorAlertOpen = true;
        }
    }

    public function closeTermsModal(): void
    {
        $this->isTermsModalOpen = false;
        $this->termsAgreed = false;
        $this->signatureData = null;
        $this->resetValidation();
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
            'kategori' => $room->getAttribute('tipe') ?: 'Lainnya',
            'kategori_label' => $room->getAttribute('tipe') ?: 'Lainnya',
            'nama' => $room->nama_ruangan,
            'kode' => $room->kode_ruangan,
            'deskripsi' => $room->getAttribute('deskripsi') ?: 'Fasilitas ruangan yang tersedia untuk peminjaman.',
            'kapasitas' => (int) ($room->kapasitas ?? 0),
            'satuan' => 'Orang',
            'icon' => $room->icon ?: 'fa-solid fa-door-closed',
            'fasilitas' => $this->parseFacilities($room->getAttribute('fasilitas') ?? ''),
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
            str_contains($facility, 'headset') || str_contains($facility, 'earphone') => 'fa-headset',
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
            $query->where(
                $this->selectedResourceType === 'ruangan'
                    ? 'room_id'
                    : 'item_id',
                $this->selectedResourceId
            );
        }

        $this->activeBorrowings = $query
            ->orderBy('id')
            ->get()
            ->map(function (BorrowingDetail $detail) {
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
            })
            ->toArray();
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
        $isRoom = $this->type === 'ruangan';
        $baseQuery = $isRoom ? Room::query() : Item::query();

        if (trim($this->search) !== '') {
            $search = trim($this->search);

            $baseQuery->where(function ($q) use ($search, $isRoom) {
                if ($isRoom) {
                    $q->where('nama_ruangan', 'like', "%{$search}%")
                        ->orWhere('kode_ruangan', 'like', "%{$search}%");
                } else {
                    $q->where('nama_barang', 'like', "%{$search}%")
                        ->orWhere('kode_barang', 'like', "%{$search}%");
                }
            });
        }

        if (
            $isRoom &&
            $this->filterTipe !== 'all' &&
            Schema::hasColumn('rooms', 'tipe')
        ) {
            $baseQuery->where('tipe', $this->filterTipe);
        }

        if (
            !$isRoom &&
            $this->filterTipe !== 'all' &&
            Schema::hasColumn('items', 'kategori')
        ) {
            $baseQuery->where('kategori', $this->filterTipe);
        }

        $categories = $isRoom
            ? collect($this->roomTypes)
            : (
                Schema::hasColumn('items', 'kategori')
                    ? Item::query()
                        ->whereNotNull('kategori')
                        ->where('kategori', '!=', '')
                        ->distinct()
                        ->orderBy('kategori')
                        ->pluck('kategori')
                    : collect()
            );

        $resources = $baseQuery
            ->orderBy($isRoom ? 'nama_ruangan' : 'nama_barang')
            ->paginate(8);

        $startDate = Carbon::parse($this->dateFilter)->startOfDay();
        $endDate = Carbon::parse($this->dateFilter)->endOfDay();
        $activeStatuses = ['Menunggu', 'Disetujui', 'Dipinjam'];

        $resources->through(function ($resource) use ($startDate, $endDate, $activeStatuses, $isRoom) {
            $item = $isRoom
                ? $this->mapRoom($resource)
                : $this->mapItem($resource);

            $detailQuery = BorrowingDetail::query()
                ->whereHas('borrowing', function ($q) use ($startDate, $endDate, $activeStatuses) {
                    $q->whereIn('status', $activeStatuses)
                        ->where('tanggal_mulai', '<', $endDate)
                        ->where('tanggal_selesai', '>', $startDate);
                });

            if ($isRoom) {
                $count = (clone $detailQuery)
                    ->where('room_id', $resource->id)
                    ->count();

                $item['booked_count'] = $count;
                $item['status_label'] = $count > 0 ? 'Ada Jadwal' : 'Tersedia';
            } else {
                $used = (int) (clone $detailQuery)
                    ->where('item_id', $resource->id)
                    ->sum('jumlah');

                $item['booked_count'] = $used;
                $item['available_qty'] = max(0, $item['kapasitas'] - $used);
                $item['status_label'] = $item['available_qty'] > 0 ? 'Tersedia' : 'Stok Habis';
            }

            return $item;
        });

        $bookingRoomsList = Room::where('status_tersedia', true)
            ->orderBy('nama_ruangan')
            ->get([
                'id',
                'nama_ruangan',
                'kode_ruangan',
            ]);

        $bookingItemsList = Item::where('bisa_dipinjam', true)
            ->orderBy('nama_barang')
            ->get([
                'id',
                'nama_barang',
                'kode_barang',
                'jumlah_total',
            ]);

        return [
            'resources' => $resources,
            'categories' => $categories,
            'bookingRoomsList' => $bookingRoomsList,
            'bookingItemsList' => $bookingItemsList,
        ];
    }
};
?>
<style>
    .hide-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    .hide-scrollbar::-webkit-scrollbar {
        display: none;
    }
</style>
<div
    class="mt-4 flex-1 w-full max-w-7xl px-4 py-8 mx-auto sm:px-6 lg:px-8"
    x-data="{
        alertOpen: @entangle('isAlertModalOpen'),
        bookingOpen: @entangle('isBookingModalOpen'),
        infoOpen: @entangle('isInfoModalOpen'),
        loginOpen: @entangle('isLoginAlertOpen'),
        errorOpen: @entangle('isErrorAlertOpen'),
        termsOpen: @entangle('isTermsModalOpen'),
        initSignature() {
            this.$nextTick(() => {
                const canvas = this.$refs.signatureCanvas;

                if (!canvas) return;

                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const rect = canvas.getBoundingClientRect();

                canvas.width = rect.width * ratio;
                canvas.height = rect.height * ratio;

                const ctx = canvas.getContext('2d');

                ctx.scale(ratio, ratio);
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.strokeStyle = '#334155';

                let drawing = false;

                const getPoint = (event) => {
                    const bounds = canvas.getBoundingClientRect();

                    return {
                        x: event.clientX - bounds.left,
                        y: event.clientY - bounds.top
                    };
                };

                canvas.onpointerdown = (event) => {
                    drawing = true;

                    const point = getPoint(event);

                    ctx.beginPath();
                    ctx.moveTo(point.x, point.y);

                    canvas.setPointerCapture(event.pointerId);
                };

                canvas.onpointermove = (event) => {
                    if (!drawing) return;

                    const point = getPoint(event);

                    ctx.lineTo(point.x, point.y);
                    ctx.stroke();
                };

                canvas.onpointerup = (event) => {
                    drawing = false;

                    if (canvas.hasPointerCapture(event.pointerId)) {
                        canvas.releasePointerCapture(event.pointerId);
                    }
                };

                canvas.onpointercancel = () => {
                    drawing = false;
                };
            });
        },

        saveSignature() {
            const canvas = this.$refs.signatureCanvas;

            if (!canvas) {
                return Promise.resolve();
            }

            return $wire.set(
                'signatureData',
                canvas.toDataURL('image/png')
            );
        },

        clearSignature() {
            const canvas = this.$refs.signatureCanvas;

            if (!canvas) return;

            const ctx = canvas.getContext('2d');

            ctx.clearRect(
                0,
                0,
                canvas.width,
                canvas.height
            );

            $wire.set('signatureData', null);
        }
    }"
>
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
                {{-- <i class="absolute text-sm -translate-y-1/2 pointer-events-none left-3 top-1/2 fa-solid fa-layer-group text-brand-500"></i> --}}
            </div>

            <div class="relative w-full sm:w-44">
                <i class="absolute text-sm -translate-y-1/2 pointer-events-none left-3 top-1/2 fa-regular fa-calendar text-slate-400"></i>
                <input type="date" wire:model.live="dateFilter" min="{{ now()->format('Y-m-d') }}" max="{{ now()->addWeek(2)->format('Y-m-d') }}" class="w-full py-2.5 pl-9 pr-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm md:w-full">
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
        <div class="p-3 mb-6 text-sm font-medium text-rose-700 border rounded-xl bg-rose-50 border-rose-200 dark:bg-rose-900/20 dark:text-rose-300 dark:border-rose-800">
            {{ $message }}
        </div>
    @enderror

    @if($categories->count())
        <div class="mb-6 overflow-x-auto border-b border-slate-200 dark:border-slate-700/80">
            <ul class="flex gap-6 text-sm font-medium min-w-max">
                <li>
                    <button
                        type="button"
                        wire:click="$set('filterTipe', 'all')"
                        class="whitespace-nowrap pb-4 border-b-2 transition-colors {{ $filterTipe === 'all' ? 'border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}"
                    >
                        Semua
                    </button>
                </li>

                @foreach($categories as $cat)
                    <li>
                        <button
                            type="button"
                            wire:click="$set('filterTipe', @js($cat))"
                            class="whitespace-nowrap pb-4 border-b-2 transition-colors {{ $filterTipe === $cat ? 'border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}"
                        >
                            {{ $cat }}
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($resources as $item)
            <div
                wire:key="resource-{{ $item['tipe'] }}-{{ $item['id'] }}"
                class="flex flex-col overflow-hidden transition-all duration-300 bg-white border border-slate-200 dark:bg-slate-800 rounded-2xl dark:border-slate-700 hover:shadow-xl hover:shadow-brand-500/5"
            >
                <div class="relative flex items-center justify-center h-36 overflow-hidden bg-slate-100 dark:bg-slate-700/50">
                    <i class="text-5xl transition-transform duration-500 fa-solid {{ str_replace('fa-solid ', '', $item['icon']) }} text-slate-300 dark:text-slate-600"></i>

                    <div class="absolute top-3 right-3 px-2.5 py-1 rounded-lg text-[10px] font-bold {{ $item['status_label'] === 'Tersedia' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' }}">
                        {{ $item['status_label'] }}
                    </div>
                </div>

                <div class="flex flex-col flex-1 p-5">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400">
                            {{ $item['kategori_label'] }}
                        </span>

                        <span class="text-[10px] font-bold text-brand-600 dark:text-brand-400">
                            #{{ $item['kode'] }}
                        </span>
                    </div>

                    <h3 class="mb-3 text-lg font-bold text-slate-900 dark:text-white">
                        {{ $item['nama'] }}
                    </h3>

                    <p class="mb-4 text-xs text-slate-500 dark:text-slate-400 line-clamp-2">
                        {{ $item['deskripsi'] }}
                    </p>

                    <div class="flex items-center gap-2 mb-4 text-xs text-slate-600 dark:text-slate-300">
                        <span class="px-2 py-1 font-medium border rounded-md bg-slate-50 dark:bg-slate-900/50 border-slate-100 dark:border-slate-700 whitespace-nowrap">
                            <i class="mr-1 fa-solid {{ $item['tipe'] === 'ruangan' ? 'fa-users' : 'fa-box' }} text-slate-400"></i>
                            {{ $item['tipe'] === 'ruangan' ? $item['kapasitas'].' Orang' : $item['kapasitas'].' Unit' }}
                        </span>
                    </div>

                    @if($item['tipe'] === 'ruangan')
                        <div class="flex gap-2 pb-1 mb-5 overflow-x-auto hide-scrollbar whitespace-nowrap snap-x" aria-label="Daftar fasilitas ruangan">
                            @forelse($item['fasilitas'] as $facility)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[10px] font-semibold text-slate-600 bg-slate-100 border border-slate-200 rounded-lg dark:bg-slate-700/70 dark:text-slate-300 dark:border-slate-600 shrink-0">
                                    <i class="fa-solid {{ $this->facilityIcon($facility) }} text-brand-500"></i>
                                    {{ $facility }}
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
                        <button
                            type="button"
                            wire:click="openInfoModal('{{ $item['tipe'] }}', {{ $item['id'] }})"
                            class="flex items-center justify-center gap-2 py-2.5 text-xs font-semibold rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
                        >
                            <i class="fa-solid fa-circle-info"></i>
                            Info
                        </button>

                        @if($item['can_borrow'])
                            <button
                                type="button"
                                wire:click="requestBooking('{{ $item['tipe'] }}', {{ $item['id'] }})"
                                class="py-2.5 text-xs font-semibold text-white rounded-xl bg-brand-600 hover:bg-brand-700 shadow-lg shadow-brand-500/20"
                            >
                                Pinjam
                            </button>
                        @else
                            <button
                                type="button"
                                disabled
                                class="py-2.5 text-xs font-semibold rounded-xl text-slate-400 bg-slate-100 dark:bg-slate-700 cursor-not-allowed"
                            >
                                Tidak Tersedia
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 text-center bg-white border border-slate-200 shadow-sm rounded-2xl dark:bg-slate-800 dark:border-slate-700">
                <i class="mb-3 text-4xl text-slate-300 fa-solid fa-box-open dark:text-slate-600"></i>
                <p class="text-sm font-semibold text-slate-400">
                    Data tidak ditemukan.
                </p>
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
                    <button wire:click="closeLoginAlert" class="flex-1 py-2.5 text-sm font-semibold rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600">Batal</button>
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

                   <div class="p-3 sm:p-4 border rounded-2xl bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700">
                        <div class="flex flex-col gap-3 mb-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">
                                        Daftar Peminjaman
                                    </h3>
                                    <span class="text-[10px] text-slate-400">
                                        {{ count($form['rooms']) + count($form['items']) }} item dipilih
                                    </span>
                                </div>
                                
                            </div>

                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-[auto_minmax(0,1fr)_auto]">
                                <select wire:model.live="additionalResourceType"
                                    class="w-full px-3 py-2.5 text-xs font-semibold border rounded-lg bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500">
                                    <option value="ruangan">Ruangan</option>
                                    <option value="barang">Barang</option>
                                </select>

                                <div wire:key="facility-select-{{ $additionalResourceType }}" wire:ignore class="min-w-0" x-data x-init="$nextTick(() => {
                                    const el = $($refs.facilitySelect).select2({
                                        placeholder: '+ Tambah peminjaman',
                                        allowClear: true,
                                        width: '100%'
                                    });

                                    el.on('change', () => $wire.set('additionalResourceId', el.val() || ''));

                                    $watch('$wire.additionalResourceId', value => {
                                        el.val(value).trigger('change.select2');
                                    });
                                })">
                                    <select x-ref="facilitySelect"
                                        class="w-full px-3 py-2.5 text-xs border rounded-lg bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700">
                                        <option value=""></option>

                                        @if($additionalResourceType === 'ruangan')
                                        @foreach($bookingRoomsList as $r)
                                        <option value="{{ $r->id }}">
                                            {{ $r->nama_ruangan }} #{{ $r->kode_ruangan }}
                                        </option>
                                        @endforeach
                                        @else
                                        @foreach($bookingItemsList as $it)
                                        <option value="{{ $it->id }}">
                                            {{ $it->nama_barang }} #{{ $it->kode_barang }}
                                        </option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>

                                <button type="button" wire:click="addAnotherResourceFromModal"
                                    class="inline-flex items-center justify-center gap-1.5 w-full sm:w-auto px-4 py-2.5 text-xs font-bold text-white rounded-lg bg-brand-600 hover:bg-brand-700 active:scale-[0.98] transition">
                                    <i class="fa-solid fa-plus"></i>
                                    <span>Tambah</span>
                                </button>
                            </div>
                        </div>

                        @error('additionalResourceId')
                        <span class="block mb-3 text-[10px] text-rose-500">
                            {{ $message }}
                        </span>
                        @enderror

                        <div class="space-y-2 max-h-[46vh] sm:max-h-80 overflow-y-auto hide-scrollbar pr-0.5 sm:pr-1">

                            {{-- RUANGAN --}}
                            @forelse($form['rooms'] as $index => $room)
                            <div wire:key="booking-room-{{ $index }}" x-data="{ open: false }"
                                class="overflow-hidden bg-white border rounded-xl dark:bg-slate-800 border-slate-200 dark:border-slate-700">
                                {{-- HEADER ACCORDION --}}
                                <div @click="open = !open"
                                    class="flex items-center gap-2.5 sm:gap-3 px-3 py-2.5 sm:px-3.5 sm:py-3 cursor-pointer select-none hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">
                                    {{-- ICON --}}
                                    <div
                                        class="flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 shrink-0">
                                        <i class="text-xs sm:text-sm fa-solid {{ $room['icon'] }}"></i>
                                    </div>

                                    {{-- INFO RUANGAN --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center min-w-0 gap-1.5">
                                            <span class="text-[8px] sm:text-[9px] font-bold uppercase text-brand-500 shrink-0">
                                                #{{ $room['kode'] }}
                                            </span>

                                            @if(!empty($room['available_fasilitas']))
                                            <span
                                                class="px-1.5 py-0.5 text-[7px] sm:text-[8px] font-semibold rounded-full bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300 shrink-0">
                                                {{ count($room['available_fasilitas']) }}
                                            </span>
                                            @endif
                                        </div>

                                        <div class="text-[11px] sm:text-xs font-bold text-slate-800 truncate dark:text-white">
                                            {{ $room['nama'] }}
                                        </div>

                                        <div class="flex items-center gap-1 mt-0.5">
                                            <i class="text-[7px] sm:text-[8px] fa-solid fa-check-circle text-brand-500"></i>
                                            <span class="text-[8px] sm:text-[9px] text-slate-400 truncate">
                                                {{ count($room['fasilitas'] ?? []) }} fasilitas dipilih
                                            </span>
                                        </div>
                                    </div>

                                    {{-- ACTION --}}
                                    <div class="flex items-center gap-1 shrink-0">
                                        <button type="button" @click.stop wire:click="openItemInfo('ruangan', {{ $room['room_id'] }})"
                                            class="inline-flex items-center justify-center w-7 h-7 sm:w-8 sm:h-8 rounded-lg text-slate-500 bg-slate-100 hover:bg-slate-200 dark:text-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600"
                                            title="Informasi">
                                            <i class="text-[9px] sm:text-[10px] fa-solid fa-circle-info"></i>
                                        </button>

                                        <button type="button" @click.stop wire:click="removeRoom({{ $index }})"
                                            class="inline-flex items-center justify-center w-7 h-7 sm:w-8 sm:h-8 text-rose-500 rounded-lg bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10"
                                            title="Hapus">
                                            <i class="text-[9px] sm:text-[10px] fa-solid fa-trash"></i>
                                        </button>

                                        <span
                                            class="flex items-center justify-center w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300">
                                            <i class="text-[9px] sm:text-[10px] fa-solid fa-chevron-down transition-transform duration-200"
                                                :class="{ 'rotate-180': open }"></i>
                                        </span>
                                    </div>
                                </div>

                                {{-- ISI --}}
                                <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1"
                                    class="border-t border-slate-100 dark:border-slate-700">
                                    @if(!empty($room['available_fasilitas']))
                                    <div class="p-3 sm:p-3.5">

                                        <div class="flex items-center justify-between gap-2.5 mb-2.5">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5">
                                                    <i class="text-[9px] fa-solid fa-list-check text-brand-500"></i>

                                                    <span class="text-[10px] sm:text-[11px] font-bold text-slate-700 dark:text-slate-200">
                                                        Fasilitas
                                                    </span>
                                                </div>

                                                <p class="mt-0.5 text-[8px] sm:text-[9px] text-slate-400">
                                                    Geser untuk melihat fasilitas.
                                                </p>
                                            </div>

                                            <button type="button" wire:click="toggleAllRoomFacilities({{ $index }})"
                                                class="inline-flex items-center justify-center gap-1 px-2 py-1.5 text-[8px] sm:text-[9px] font-bold rounded-lg text-brand-600 bg-brand-50 hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/20 shrink-0">
                                                <i class="fa-solid fa-check-double"></i>

                                                <span class="hidden xs:inline">
                                                    @if(count($room['fasilitas'] ?? []) === count($room['available_fasilitas']))
                                                    Batal semua
                                                    @else
                                                    Pilih semua
                                                    @endif
                                                </span>

                                                <span class="xs:hidden">
                                                    @if(count($room['fasilitas'] ?? []) === count($room['available_fasilitas']))
                                                    Batal
                                                    @else
                                                    Semua
                                                    @endif
                                                </span>
                                            </button>
                                        </div>

                                        {{-- CHIP HORIZONTAL --}}
                                        <div class="relative -mx-1">
                                            <div class="flex gap-1.5 px-1 pb-1 overflow-x-auto hide-scrollbar snap-x snap-mandatory">
                                                @foreach($room['available_fasilitas'] as $facility)
                                                @php
                                                $selected = in_array($facility, $room['fasilitas'] ?? []);
                                                @endphp

                                                <label class="flex items-center gap-1.5 min-w-max px-2.5 py-1.5 sm:px-3 sm:py-2 border rounded-full cursor-pointer snap-start transition-all duration-150
                                                            {{ $selected
                                                                ? 'border-brand-300 bg-brand-50 text-brand-700 dark:border-brand-500/50 dark:bg-brand-500/10 dark:text-brand-300'
                                                                : 'border-slate-200 bg-white text-slate-600 hover:border-brand-300 hover:bg-brand-50/50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-brand-500/50'
                                                            }}">
                                                    <input type="checkbox" wire:model="form.rooms.{{ $index }}.fasilitas"
                                                        value="{{ $facility }}"
                                                        class="w-3 h-3 sm:w-3.5 sm:h-3.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-700">

                                                    <i
                                                        class="fa-solid {{ $this->facilityIcon($facility) }} text-[9px] sm:text-[10px] text-brand-500"></i>

                                                    <span class="text-[9px] sm:text-[10px] font-semibold">
                                                        {{ $facility }}
                                                    </span>
                                                </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    @else
                                    <div class="px-4 py-5 text-center">
                                        <i class="mb-1 text-sm fa-solid fa-box-open text-slate-300 dark:text-slate-600"></i>
                                        <p class="text-[9px] font-medium text-slate-400">
                                            Tidak ada fasilitas tambahan.
                                        </p>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @empty
                            @endforelse

                            {{-- BARANG --}}
                            @foreach($form['items'] as $index => $item)
                            <div wire:key="booking-item-{{ $index }}"
                                class="p-3 sm:p-3.5 bg-white border rounded-xl dark:bg-slate-800 border-slate-200 dark:border-slate-700">
                                <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="text-[8px] sm:text-[9px] font-bold uppercase text-brand-500">
                                            #{{ $item['kode'] }}
                                        </div>

                                        <div class="text-[11px] sm:text-xs font-bold text-slate-800 truncate dark:text-white">
                                            {{ $item['nama'] }}
                                        </div>

                                        <div class="text-[8px] sm:text-[9px] text-slate-400">
                                            Stok {{ $item['stok'] }} unit
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-[1fr_auto_auto] gap-1.5 sm:flex sm:items-center">
                                        <input type="number" wire:model="form.items.{{ $index }}.jumlah" min="1" max="{{ $item['stok'] }}"
                                            class="w-full sm:w-20 px-2.5 py-2 text-xs border rounded-lg bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700">

                                        <button type="button" wire:click="openItemInfo('barang', {{ $item['item_id'] }})"
                                            class="inline-flex items-center justify-center w-8 h-8 text-slate-500 bg-slate-100 rounded-lg hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200"
                                            title="Informasi">
                                            <i class="text-[10px] fa-solid fa-circle-info"></i>
                                        </button>

                                        <button type="button" wire:click="removeItem({{ $index }})"
                                            class="inline-flex items-center justify-center w-8 h-8 text-rose-500 rounded-lg bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10"
                                            title="Hapus">
                                            <i class="text-[10px] fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </div>

                                @error('form.items.'.$index.'.jumlah')
                                <span class="block mt-1 text-[10px] text-rose-500">
                                    {{ $message }}
                                </span>
                                @enderror
                            </div>
                            @endforeach

                            @if(empty($form['rooms']) && empty($form['items']))
                            <div class="py-8 text-center border border-dashed rounded-xl border-slate-300 dark:border-slate-600">
                                <i class="mb-2 text-xl fa-solid fa-list-check text-slate-400"></i>
                                <p class="text-xs font-semibold text-slate-400">
                                    Belum ada fasilitas dipilih.
                                </p>
                            </div>
                            @endif
                        </div>
                    </div>

                    <div><label class="block mb-1.5 text-xs font-semibold">Tujuan Kegiatan <span class="text-rose-500">*</span></label><textarea rows="3" wire:model="form.tujuan" placeholder="Contoh : Rapat" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm resize-none" required></textarea>@error('form.tujuan')<span class="text-[10px] text-rose-500">{{ $message }}</span>@enderror</div>
                    <div><label class="block mb-1.5 text-xs font-semibold">Catatan penggunaan ruang <span class="text-rose-500">*</span></label><input type="text" placeholder="Contoh: Ruangan digunakan untuk transit" wire:model="form.catatan" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm">@error('form.catatan')<span class="text-[10px] text-rose-500">{{ $message }}</span>@enderror</div>

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
                                    <div class="flex items-center gap-3 p-4 text-xs font-medium text-slate-600 border border-dashed rounded-lg bg-white dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700">
                                        <i class="text-2xl fa-solid fa-file-image text-rose-500"></i>
                                        <div>Image terpilih.</div>
                                    </div>
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

    {{-- Modal Terms --}}
    <template x-teleport="body">
        <div x-show="termsOpen" x-cloak
            class="fixed inset-0 z-[9999] flex items-center justify-center p-2 sm:p-4 bg-slate-950/75 backdrop-blur-sm">
            <div x-show="termsOpen" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                class="relative flex flex-col w-full max-w-2xl max-h-[96vh] overflow-hidden bg-white shadow-2xl dark:bg-slate-800 rounded-2xl sm:rounded-3xl">
                {{-- HEADER --}}
                <div
                    class="flex items-center justify-between gap-4 px-4 py-4 sm:px-6 border-b border-slate-200 dark:border-slate-700">
                    <div class="flex items-center min-w-0 gap-3">
                        <div
                            class="flex items-center justify-center w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-brand-100 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400 shrink-0">
                            <i class="text-lg fa-solid fa-file-signature"></i>
                        </div>

                        <div class="min-w-0">
                            <h3 class="text-base sm:text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                                Syarat & Ketentuan Peminjaman
                            </h3>

                            <p class="mt-1 text-[10px] sm:text-xs font-medium text-slate-500 dark:text-slate-400">
                                Wajib dibaca dan disetujui sebelum pengajuan.
                            </p>
                        </div>
                    </div>

                    <button type="button" wire:click="closeTermsModal"
                        class="flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-slate-100 text-slate-500 hover:bg-rose-50 hover:text-rose-500 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-rose-500/10 dark:hover:text-rose-400 transition-colors shrink-0"
                        aria-label="Tutup">
                        <i class="text-sm fa-solid fa-xmark"></i>
                    </button>
                </div>

                {{-- CONTENT --}}
                <div class="flex-1 px-4 py-4 space-y-5 overflow-y-auto sm:px-6 sm:py-5 hide-scrollbar">

                    {{-- PERINGATAN --}}
                    <div
                        class="flex gap-3 p-4 sm:p-5 border-2 rounded-2xl bg-amber-50 border-amber-200 dark:bg-amber-500/5 dark:border-amber-500/30">
                        <div
                            class="flex items-center justify-center w-9 h-9 rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 shrink-0">
                            <i class="text-sm fa-solid fa-triangle-exclamation"></i>
                        </div>

                        <div class="min-w-0">
                            <p class="text-xs sm:text-sm font-extrabold text-amber-800 dark:text-amber-300">
                                Perhatian — Persetujuan Wajib
                            </p>

                            <p
                                class="mt-1 text-[10px] sm:text-xs font-medium leading-relaxed text-amber-700 dark:text-amber-300/80">
                                Anda harus membaca seluruh ketentuan, mencentang persetujuan,
                                dan membubuhkan tanda tangan digital sebelum peminjaman dapat diajukan.
                            </p>
                        </div>
                    </div>

                    {{-- KETENTUAN --}}
                    <div>
                        <div class="flex items-end justify-between gap-3 mb-3">
                            <div>
                                <h4 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">
                                    Ketentuan Peminjaman
                                </h4>

                                <p class="mt-0.5 text-[10px] sm:text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Pastikan Anda memahami setiap ketentuan berikut.
                                </p>
                            </div>

                            <span
                                class="shrink-0 px-2.5 py-1.5 text-[9px] sm:text-[10px] font-bold rounded-full bg-brand-100 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                WAJIB DIBACA
                            </span>
                        </div>

                        <div
                            class="p-4 sm:p-5 border-2 rounded-2xl bg-slate-50 border-slate-200 dark:bg-slate-900/40 dark:border-slate-700">
                            <ol
                                class="space-y-4 text-[11px] sm:text-sm font-semibold leading-relaxed text-slate-700 dark:text-slate-200">
                                <li class="flex gap-3">
                                    <span
                                        class="flex items-center justify-center w-7 h-7 text-[10px] sm:text-xs font-extrabold rounded-full bg-brand-600 text-white shrink-0">
                                        1
                                    </span>
                                    <span class="pt-0.5">
                                        Peminjaman harus digunakan sesuai tujuan yang diajukan.
                                    </span>
                                </li>

                                <li class="flex gap-3">
                                    <span
                                        class="flex items-center justify-center w-7 h-7 text-[10px] sm:text-xs font-extrabold rounded-full bg-brand-600 text-white shrink-0">
                                        2
                                    </span>
                                    <span class="pt-0.5">
                                        Pengguna bertanggung jawab terhadap kondisi ruangan dan fasilitas yang digunakan.
                                    </span>
                                </li>

                                <li class="flex gap-3">
                                    <span
                                        class="flex items-center justify-center w-7 h-7 text-[10px] sm:text-xs font-extrabold rounded-full bg-brand-600 text-white shrink-0">
                                        3
                                    </span>
                                    <span class="pt-0.5">
                                        Peminjam wajib mematuhi jadwal yang telah disetujui.
                                    </span>
                                </li>

                                <li class="flex gap-3">
                                    <span
                                        class="flex items-center justify-center w-7 h-7 text-[10px] sm:text-xs font-extrabold rounded-full bg-brand-600 text-white shrink-0">
                                        4
                                    </span>
                                    <span class="pt-0.5">
                                        Kerusakan atau kehilangan fasilitas menjadi tanggung jawab peminjam sesuai ketentuan
                                        yang berlaku.
                                    </span>
                                </li>

                                <li class="flex gap-3">
                                    <span
                                        class="flex items-center justify-center w-7 h-7 text-[10px] sm:text-xs font-extrabold rounded-full bg-brand-600 text-white shrink-0">
                                        5
                                    </span>
                                    <span class="pt-0.5">
                                        Pengajuan akan diproses setelah disetujui oleh admin.
                                    </span>
                                </li>
                            </ol>
                        </div>
                    </div>

                    {{-- PERSETUJUAN --}}
                    <div>
                        <label class="flex items-start gap-3 p-4 sm:p-5 border-2 rounded-2xl cursor-pointer transition-all
                        {{ $termsAgreed
                            ? 'border-brand-500 bg-brand-50 dark:border-brand-500 dark:bg-brand-500/10'
                            : 'border-rose-200 bg-rose-50/50 hover:border-brand-400 dark:border-slate-700 dark:bg-slate-900/30'
                        }}">
                            <input type="checkbox" wire:model.live="termsAgreed"
                                class="w-5 h-5 mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-700 shrink-0"
                                required>

                            <span class="flex-1 min-w-0">
                                <span class="block text-xs sm:text-sm font-extrabold text-slate-800 dark:text-white">
                                    Saya menyetujui seluruh Syarat & Ketentuan
                                    <span class="text-rose-500">*</span>
                                </span>

                                <span
                                    class="block mt-1 text-[10px] sm:text-xs font-medium leading-relaxed text-slate-600 dark:text-slate-400">
                                    Dengan mencentang pilihan ini, saya menyatakan telah membaca,
                                    memahami, dan bersedia mematuhi seluruh ketentuan peminjaman.
                                </span>
                            </span>

                            <i
                                class="mt-0.5 text-base fa-solid {{ $termsAgreed ? 'fa-circle-check text-brand-600' : 'fa-circle text-slate-300 dark:text-slate-600' }} shrink-0"></i>
                        </label>
                        @error('termsAgreed')
                        <div class="flex items-center gap-1.5 mt-2 text-[10px] sm:text-xs font-bold text-rose-500">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <span>{{ $message }}</span>
                        </div>
                        @enderror
                    </div>
                    {{-- TANDA TANGAN --}}
                    <div>
                        <div class="flex items-end justify-between gap-3 mb-3">
                            <div>
                                <h4 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">
                                    Tanda Tangan Digital
                                    <span class="text-rose-500">*</span>
                                </h4>

                                <p class="mt-0.5 text-[10px] sm:text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Tanda tangan wajib diisi sebelum pengajuan.
                                </p>
                            </div>

                            <button type="button" x-on:click="clearSignature()"
                                class="inline-flex items-center gap-1.5 px-3 py-2 text-[10px] font-bold text-rose-600 rounded-lg bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-400 dark:hover:bg-rose-500/20 shrink-0">
                                <i class="fa-solid fa-rotate-left"></i>
                                Hapus
                            </button>
                        </div>
                        <div
                            class="relative overflow-hidden bg-white border-2 rounded-2xl border-slate-300 dark:border-slate-600 ring-1 ring-slate-100 dark:ring-slate-700">
                            <div
                                class="absolute top-0 left-0 right-0 flex items-center justify-between px-4 py-3 pointer-events-none">
                                <span class="text-[9px] sm:text-[10px] font-semibold text-slate-300">
                                    Tanda tangan di area ini
                                </span>
                                <i class="text-[10px] fa-solid fa-pen-nib text-slate-300"></i>
                            </div>
                            <canvas x-ref="signatureCanvas" x-init="initSignature()"
                                class="w-full h-44 sm:h-52 touch-none cursor-crosshair"></canvas>
                        </div>
                        @error('signatureData')
                        <div class="flex items-center gap-1.5 mt-2 text-[10px] sm:text-xs font-bold text-rose-500">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <span>{{ $message }}</span>
                        </div>
                        @enderror
                    </div>
                </div>
                {{-- FOOTER --}}
                <div
                    class="px-4 py-4 sm:px-6 sm:py-5 border-t-2 border-slate-100 bg-white dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex flex-col-reverse gap-2.5 sm:flex-row">
                        <button type="button" wire:click="closeTermsModal"
                            class="flex-1 py-3.5 text-sm font-bold rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600 transition-colors">
                            Kembali
                        </button>

                        <button type="button" x-on:click="saveSignature()" wire:click="confirmBooking"
                            class="flex-[2] flex items-center justify-center gap-2 py-3.5 text-sm font-extrabold text-white rounded-xl bg-brand-600 hover:bg-brand-700 active:scale-[0.99] transition-all shadow-lg shadow-brand-500/20">
                            <i class="fa-solid fa-paper-plane"></i>
                            Setuju & Ajukan Peminjaman
                        </button>
                    </div>

                    <p class="mt-2 text-center text-[9px] sm:text-[10px] font-medium text-slate-400">
                        <i class="mr-1 fa-solid fa-lock"></i>
                        Pengajuan hanya dapat dilakukan setelah persetujuan dan tanda tangan diisi.
                    </p>
                </div>
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

