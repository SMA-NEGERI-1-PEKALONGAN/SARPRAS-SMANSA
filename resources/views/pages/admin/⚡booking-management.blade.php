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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

new #[Layout('layouts.app')] #[Title('Dashboard Peminjaman Grid')] class extends Component
{
    use WithPagination, WithFileUploads;

    // Filter grid
    public string $search = '';
    public string $dateFrom;
    public string $dateTo;
    public string $tab = 'room';

    // Global Info Jadwal
    public bool $isScheduleModalOpen = false;
    public string $scheduleSearch = '';

    // Modal detail transaksi
    public bool $isBorrowingDetailModalOpen = false;
    public array $selectedBorrowing = [];

    // Resource detail
    public bool $isDetailModalOpen = false;
    public ?int $selectedResourceId = null;
    public string $selectedResourceName = '';
    public string $selectedResourceType = 'room';
    public array $activeBorrowings = [];

    // Add booking
    public bool $isAddModalOpen = false;
    public array $form = [
        'user_id' => '',
        'tanggal_mulai' => '',
        'tanggal_selesai' => '',
        'tujuan' => '',
        'rooms' => [ ['room_id' => ''] ],
        'items' => [ ['item_id' => '', 'jumlah' => 0] ],
    ];

    // Approval reusable modal
    public bool $isApprovalModalOpen = false;
    public ?int $approvalBorrowingId = null;
    public string $approvalTransactionCode = '';
    public string $approvalCurrentStatus = 'Menunggu';
    public string $approvalStatus = 'Menunggu';
    public string $catatan_admin = '';
    public $approvalFile = null;
    public $formFile = null;
    public array $approvalDetails = [];

    public array $statusOptions = ['Menunggu', 'Disetujui', 'Ditolak', 'Dipinjam', 'Dikembalikan'];

    public function mount(): void
    {
        $this->dateFrom = now()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingScheduleSearch(): void { $this->resetPage('schedulePage'); }
    public function updatingDateFrom(): void { $this->resetPage(); $this->refreshOpenDetail(); }
    public function updatingDateTo(): void { $this->resetPage(); $this->refreshOpenDetail(); }
    public function updatingTab(): void { $this->resetPage(); }

    protected function dateRange(): array
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    protected function activeStatuses(): array
    {
        return ['Menunggu', 'Disetujui', 'Dipinjam'];
    }

    public function reloadData(bool $notify = true): void
    {
        $this->refreshOpenDetail();

        if ($notify) {
            $this->dispatch('toast', type: 'success', message: 'Data berhasil diperbarui.');
        }
    }
    

    public function updatedApprovalStatus(string $value): void
    {
        $allowed = $this->approvalStatusOptions();
        if (!in_array($value, $allowed, true)) {
            return;
        }

        foreach ($this->approvalDetails as $index => $detail) {
            $original = $detail['status_original'] ?? $detail['status'];

            if ($this->approvalCurrentStatus === 'Menunggu' && $original === 'Menunggu') {
                $this->approvalDetails[$index]['status'] = $value;
            } elseif ($this->approvalCurrentStatus === 'Disetujui' && $original === 'Disetujui' && $value === 'Dikembalikan') {
                $this->approvalDetails[$index]['status'] = 'Dikembalikan';
            }
        }
    }

    public function with(): array
    {
        [$startDate, $endDate] = $this->dateRange();
        $activeStatuses = $this->activeStatuses();
        $table = $this->tab === 'room' ? 'rooms' : 'items';
        $nameColumn = $this->tab === 'room' ? 'nama_ruangan' : 'nama_barang';
        $codeColumn = $this->tab === 'room' ? 'kode_ruangan' : 'kode_barang';
        $foreignColumn = $this->tab === 'room' ? 'room_id' : 'item_id';

        $query = $this->tab === 'room' ? Room::query() : Item::query();

        $query->when($this->search, function ($q) use ($nameColumn, $codeColumn) {
            $q->where(function ($inner) use ($nameColumn, $codeColumn) {
                $inner->where($nameColumn, 'like', "%{$this->search}%")
                    ->orWhere($codeColumn, 'like', "%{$this->search}%");
            });
        });

        $pendingSubquery = BorrowingDetail::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn("borrowing_details.{$foreignColumn}", "{$table}.id")
            ->where('status', 'Menunggu')
            ->whereHas('borrowing', function ($q) use ($startDate, $endDate) {
                $q->where('tanggal_mulai', '<', $endDate)
                    ->where('tanggal_selesai', '>', $startDate)
                    ->where('status', 'Menunggu');
            });

        $earliestSubquery = Borrowing::query()
            ->select('tanggal_mulai')
            ->whereIn('status', $activeStatuses)
            ->where('tanggal_mulai', '<', $endDate)
            ->where('tanggal_selesai', '>', $startDate)
            ->whereExists(function ($sub) use ($table, $foreignColumn) {
                $sub->selectRaw('1')
                    ->from('borrowing_details')
                    ->whereColumn('borrowing_details.borrowing_id', 'borrowings.id')
                    ->whereColumn("borrowing_details.{$foreignColumn}", "{$table}.id");
            })
            ->orderBy('tanggal_mulai')
            ->limit(1);

        $activeSubquery = BorrowingDetail::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn("borrowing_details.{$foreignColumn}", "{$table}.id")
            ->whereHas('borrowing', function ($q) use ($startDate, $endDate, $activeStatuses) {
                $q->whereIn('status', $activeStatuses)
                    ->where('tanggal_mulai', '<', $endDate)
                    ->where('tanggal_selesai', '>', $startDate);
            });

        $resources = $query
            ->select("{$table}.*")
            ->selectSub($pendingSubquery, 'pending_count')
            ->selectSub($activeSubquery, 'total_active_count')
            ->selectSub($earliestSubquery, 'earliest_booking_date')
            ->orderByDesc('pending_count')
            ->orderByRaw('earliest_booking_date IS NULL')
            ->orderBy('earliest_booking_date')
            ->orderBy($nameColumn)
            ->paginate(9, ['*'], 'resourcesPage');

        $pendingScheduleCount = Borrowing::where('status', 'Menunggu')
            ->where('tanggal_mulai', '<', $endDate)
            ->where('tanggal_selesai', '>', $startDate)
            ->count();

        return [
            'resources' => $resources,
            'pendingScheduleCount' => $pendingScheduleCount,
            'usersList' => User::orderBy('name')->get(),
            'roomsList' => Room::where('status_tersedia', true)->orderBy('nama_ruangan')->get(),
            'itemsList' => Item::where('bisa_dipinjam', true)->orderBy('nama_barang')->get(),
        ];
    }

    protected function scheduleQuery(bool $onlyPending = false)
    {
        [$startDate, $endDate] = $this->dateRange();

        return Borrowing::query()
            ->with(['user', 'details.room', 'details.item'])
            ->where('tanggal_mulai', '<', $endDate)
            ->where('tanggal_selesai', '>', $startDate)
            ->when($onlyPending, fn ($q) => $q->where('status', 'Menunggu'))
            ->when($this->scheduleSearch, function ($q) {
                $term = "%{$this->scheduleSearch}%";
                $q->where(function ($inner) use ($term) {
                    $inner->where('kode_transaksi', 'like', $term)
                        ->orWhere('tujuan', 'like', $term)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term));
                });
            })
            ->orderByRaw("CASE WHEN status = 'Menunggu' THEN 0 ELSE 1 END")
            ->orderBy('tanggal_mulai')
            ->orderBy('id');
    }

    public function openScheduleModal(): void
    {
        $this->isScheduleModalOpen = true;
        $this->resetPage('schedulePage');
    }

    public function closeScheduleModal(): void
    {
        $this->isScheduleModalOpen = false;
        $this->scheduleSearch = '';
        $this->resetPage('schedulePage');
    }

    public function openDetailModal(string $type, int $id, string $name): void
    {
        $this->selectedResourceType = $type === 'item' ? 'item' : 'room';
        $this->selectedResourceId = $id;
        $this->selectedResourceName = $name;
        $this->loadBorrowingData();
        $this->isDetailModalOpen = true;
    }

    public function closeDetailModal(): void
    {
        $this->isDetailModalOpen = false;
        $this->selectedResourceId = null;
        $this->activeBorrowings = [];
    }

    protected function refreshOpenDetail(): void
    {
        if ($this->isDetailModalOpen) {
            $this->loadBorrowingData();
        }
    }

    public function loadBorrowingData(): void
    {
        if (!$this->selectedResourceId) {
            $this->activeBorrowings = [];
            return;
        }

        [$startDate, $endDate] = $this->dateRange();
        $column = $this->selectedResourceType === 'room' ? 'room_id' : 'item_id';

        $this->activeBorrowings = BorrowingDetail::with(['borrowing.user'])
            ->where($column, $this->selectedResourceId)
            ->whereHas('borrowing', function ($q) use ($startDate, $endDate) {
                $q->where('tanggal_mulai', '<', $endDate)
                    ->where('tanggal_selesai', '>', $startDate);
            })
            ->orderByRaw("CASE WHEN status = 'Menunggu' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get()
            ->map(fn (BorrowingDetail $detail) => [
                'id' => $detail->id,
                'borrowing_id' => $detail->borrowing_id,
                'kode_transaksi' => $detail->borrowing?->kode_transaksi ?? '-',
                'peminjam' => $detail->borrowing?->user?->name ?? '-',
                'no_hp' => $detail->borrowing?->user?->no_hp ?? $detail->borrowing?->user?->no_wa ?? '-',
                'tujuan' => $detail->borrowing?->tujuan ?? '-',
                'tanggal_mulai' => optional($detail->borrowing?->tanggal_mulai)->format('d M Y H:i'),
                'tanggal_selesai' => optional($detail->borrowing?->tanggal_selesai)->format('d M Y H:i'),
                'status' => $detail->status,
                'jumlah' => $detail->jumlah,
            ])->toArray();
    }

    public function openBorrowingDetailModal(int $borrowingId): void
    {
        $borrowing = Borrowing::with(['user', 'details.room', 'details.item'])->findOrFail($borrowingId);

        $this->selectedBorrowing = [
            'id' => $borrowing->id,
            'kode_transaksi' => $borrowing->kode_transaksi,
            'status' => $borrowing->status,
            'nama' => $borrowing->user?->name ?? '-',
            'no_hp' => $borrowing->user?->no_hp ?? $borrowing->user?->no_wa ?? '-',
            'tujuan' => $borrowing->tujuan ?? '-',
            'tanggal_mulai' => optional($borrowing->tanggal_mulai)->format('d M Y H:i'),
            'tanggal_selesai' => optional($borrowing->tanggal_selesai)->format('d M Y H:i'),
            'catatan_admin' => $borrowing->getAttribute('catatan_admin') ?? '',
            'file_lampiran' => $borrowing->getAttribute('file_lampiran') ?? null,
            'details' => $borrowing->details->map(fn ($detail) => [
                'id' => $detail->id,
                'type' => $detail->room ? 'Ruangan' : 'Barang',
                'name' => $detail->room?->nama_ruangan ?? $detail->item?->nama_barang ?? '-',
                'code' => $detail->room?->kode_ruangan ?? $detail->item?->kode_barang ?? '-',
                'jumlah' => $detail->jumlah,
                'status' => $detail->status,
                'file_bukti_pengembalian' => $detail->getAttribute('file_bukti_pengembalian') ?? null,
            ])->toArray(),
        ];

        $this->isBorrowingDetailModalOpen = true;
    }

    public function closeBorrowingDetailModal(): void
    {
        $this->isBorrowingDetailModalOpen = false;
        $this->selectedBorrowing = [];
    }

    public function openApprovalModal(int $borrowingId): void
    {
        $borrowing = Borrowing::with(['user', 'details.room', 'details.item'])->findOrFail($borrowingId);

        $this->approvalBorrowingId = $borrowing->id;
        $this->approvalTransactionCode = $borrowing->kode_transaksi;
        $this->approvalCurrentStatus = $borrowing->status;
        $this->approvalStatus = $borrowing->status;
        $this->catatan_admin = (string) ($borrowing->getAttribute('catatan_admin') ?? '');
        $this->approvalFile = null;

        $this->approvalDetails = $borrowing->details->map(fn ($detail) => [
            'id' => $detail->id,
            'type' => $detail->room ? 'Ruangan' : 'Barang',
            'name' => $detail->room?->nama_ruangan ?? $detail->item?->nama_barang ?? '-',
            'code' => $detail->room?->kode_ruangan ?? $detail->item?->kode_barang ?? '-',
            'jumlah' => (int) $detail->jumlah,
            'status' => $detail->status,
            'status_original' => $detail->status,
            'file_bukti_pengembalian' => $detail->getAttribute('file_bukti_pengembalian') ?? null,
        ])->toArray();

        $this->resetValidation();
        $this->isApprovalModalOpen = true;
    }

    public function closeApprovalModal(): void
    {
        $this->isApprovalModalOpen = false;
        $this->approvalBorrowingId = null;
        $this->approvalTransactionCode = '';
        $this->approvalStatus = 'Menunggu';
        $this->approvalCurrentStatus = 'Menunggu';
        $this->catatan_admin = '';
        $this->approvalFile = null;
        $this->approvalDetails = [];
        $this->resetValidation();
    }

    public function approvalStatusOptions(): array
    {
        return match ($this->approvalCurrentStatus) {
            'Menunggu' => ['Menunggu', 'Disetujui', 'Ditolak'],
            'Disetujui' => ['Disetujui', 'Dikembalikan'],
            default => [],
        };
    }

    public function saveApproval(): void
    {
        $options = $this->approvalStatusOptions();

        $this->validate([
            'approvalStatus' => ['required', Rule::in($options)],
            'catatan_admin' => ['nullable', 'string', 'max:2000'],
            'approvalFile' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:1024'],
            'approvalDetails.*.jumlah' => ['required', 'integer', 'min:1'],
            'approvalDetails.*.status' => ['required', 'string'],
        ], [
            'approvalFile.max' => 'Lampiran maksimal 1 MB.',
            'approvalFile.mimes' => 'Lampiran harus berupa PDF atau gambar.',
        ]);

        $borrowing = Borrowing::with('details')->findOrFail($this->approvalBorrowingId);

        try {
            DB::transaction(function () use ($borrowing) {
                $detailsById = $borrowing->details->keyBy('id');

                foreach ($this->approvalDetails as $payload) {
                    $detail = $detailsById->get((int) ($payload['id'] ?? 0));
                    if (!$detail) {
                        continue;
                    }

                    $current = (string) $detail->status;
                    $requested = (string) ($payload['status'] ?? $current);

                    if ($this->approvalCurrentStatus === 'Menunggu') {
                        if (!in_array($requested, ['Menunggu', 'Disetujui', 'Ditolak'], true)) {
                            throw new \RuntimeException('Status detail tidak valid.');
                        }
                        if ($current !== 'Menunggu') {
                            $requested = $current;
                        }
                    } elseif ($this->approvalCurrentStatus === 'Disetujui') {
                        if ($current === 'Disetujui') {
                            if (!in_array($requested, ['Disetujui', 'Dikembalikan'], true)) {
                                throw new \RuntimeException('Status tindak lanjut tidak valid.');
                            }
                        } else {
                            $requested = $current;
                        }
                    } else {
                        $requested = $current;
                    }

                    $update = ['status' => $requested];

                    if ($detail->item_id !== null) {
                        $update['jumlah'] = max(1, (int) ($payload['jumlah'] ?? $detail->jumlah));
                    }

                    $detail->update($update);
                }

                $borrowing->refresh()->load('details');
                $this->syncBorrowingStatus($borrowing);

                if (Schema::hasColumn('borrowings', 'approved_by') && $this->approvalCurrentStatus === 'Menunggu') {
                    $borrowing->approved_by = auth()->id();
                }

                if (Schema::hasColumn('borrowings', 'catatan_admin')) {
                    $borrowing->catatan_admin = $this->catatan_admin ?: null;
                }

                if ($this->approvalFile && Schema::hasColumn('borrowings', 'file_lampiran')) {
                    $borrowing->file_lampiran = $this->approvalFile->store('bukti-peminjaman', 'public');
                }

                $borrowing->save();
            });

            $code = $this->approvalTransactionCode;
            $status = Borrowing::find($this->approvalBorrowingId)?->status ?? '-';

            $this->closeApprovalModal();
            $this->dispatch('toast', type: 'success', message: "Data {$code} berhasil disimpan. Status transaksi: {$status}.");
            $this->refreshOpenDetail();
        } catch (\Throwable $e) {
            report($e);
            $this->addError('approvalStatus', 'Terjadi kesalahan saat menyimpan data. Silakan periksa isian dan coba lagi.');
        }
    }

    protected function syncBorrowingStatus(Borrowing $borrowing): void
    {
        $statuses = $borrowing->details->pluck('status')->filter()->values();
        if ($statuses->isEmpty()) {
            return;
        }

        if ($statuses->every(fn ($status) => $status === 'Dikembalikan')) {
            $borrowing->status = 'Selesai';
            return;
        }

        if ($statuses->every(fn ($status) => $status === 'Ditolak')) {
            $borrowing->status = 'Ditolak';
            return;
        }

        if ($statuses->contains('Menunggu')) {
            $borrowing->status = 'Menunggu';
            return;
        }

        if ($statuses->contains('Dipinjam')) {
            $borrowing->status = 'Dipinjam';
            return;
        }

        if ($statuses->contains('Disetujui')) {
            $borrowing->status = 'Disetujui';
            return;
        }

        if ($statuses->every(fn ($status) => in_array($status, ['Ditolak', 'Dikembalikan'], true))) {
            $borrowing->status = 'Selesai';
        }
    }

    protected function isAllowedTransition(string $current, string $next): bool
    {
        $allowed = [
            'Menunggu' => ['Menunggu', 'Disetujui', 'Ditolak'],
            'Disetujui' => ['Disetujui', 'Dikembalikan'],
            'Dipinjam' => ['Dipinjam', 'Dikembalikan'],
            'Ditolak' => ['Ditolak'],
            'Dikembalikan' => ['Dikembalikan', 'Selesai'],
            'Selesai' => ['Selesai'],
        ];

        return in_array($next, $allowed[$current] ?? [], true);
    }

    public function updatedForm($value, $key): void
    {
        if (str_ends_with($key, '.item_id')) {
            $index = (int) str_replace('.item_id', '', str_replace('items.', '', $key));
            $itemId = $this->form['items'][$index]['item_id'] ?? null;
            $stock = $itemId ? (int) (Item::find($itemId)?->jumlah_total ?? 0) : 0;
            $this->form['items'][$index]['jumlah'] = $stock > 0 ? 1 : 0;
            return;
        }

        if (str_ends_with($key, '.jumlah')) {
            $index = (int) str_replace('.jumlah', '', str_replace('items.', '', $key));
            $itemId = $this->form['items'][$index]['item_id'] ?? null;
            $stock = $itemId ? (int) (Item::find($itemId)?->jumlah_total ?? 0) : 0;
            $qty = (int) ($this->form['items'][$index]['jumlah'] ?? 0);
            $this->form['items'][$index]['jumlah'] = $stock > 0 ? min(max($qty, 1), $stock) : 0;
        }
    }

    protected function itemStock($itemId): int
    {
        if ($itemId === null || $itemId === '') {
            return 0;
        }

        $itemId = (int) $itemId;

        return (int) (Item::find($itemId)?->jumlah_total ?? 0);
    }

    public function openAddModal(): void
    {
        $this->reset('form');
        $this->form = [
            'user_id' => '',
            'tanggal_mulai' => '',
            'tanggal_selesai' => '',
            'tujuan' => '',
            'rooms' => [ ['room_id' => ''] ],
            'items' => [ ['item_id' => '', 'jumlah' => 0] ],
        ];
        $this->formFile = null;
        $this->isAddModalOpen = true;
    }

    public function closeAddModal(): void
    {
        $this->isAddModalOpen = false;
        $this->formFile = null;
        $this->resetValidation();
    }

    public function addRoomRow(): void
    {
        $this->form['rooms'][] = ['room_id' => ''];
    }

    public function removeRoomRow(int $index): void
    {
        if (count($this->form['rooms']) > 1) {
            unset($this->form['rooms'][$index]);
            $this->form['rooms'] = array_values($this->form['rooms']);
            return;
        }

        $this->form['rooms'][0] = ['room_id' => ''];
    }

    public function addItemRow(): void
    {
        $this->form['items'][] = ['item_id' => '', 'jumlah' => 0];
    }

    public function removeItemRow(int $index): void
    {
        if (count($this->form['items']) > 1) {
            unset($this->form['items'][$index]);
            $this->form['items'] = array_values($this->form['items']);
            return;
        }

        $this->form['items'][0] = ['item_id' => '', 'jumlah' => 0];
    }

    public function saveBooking(): void
    {
        $rooms = array_values(array_filter($this->form['rooms'], fn ($row) => !empty($row['room_id'])));
        $items = array_values(array_filter($this->form['items'], fn ($row) => !empty($row['item_id'])));

        if (!$rooms && !$items) {
            $this->addError('form.rooms', 'Pilih minimal satu ruangan atau satu barang.');
            return;
        }

        foreach ($items as $index => $item) {
            $stock = $this->itemStock((int) $item['item_id']);
            $qty = (int) ($item['jumlah'] ?? 0);
            if ($stock < 1 || $qty < 1 || $qty > $stock) {
                $this->addError("form.items.{$index}.jumlah", "Jumlah harus antara 1 sampai {$stock}.");
                return;
            }
        }

        $this->form['rooms'] = $rooms;
        $this->form['items'] = $items;

        $this->validate([
            'form.user_id' => ['required', 'exists:users,id'],
            'form.tanggal_mulai' => ['required', 'date'],
            'form.tanggal_selesai' => ['required', 'date', 'after_or_equal:form.tanggal_mulai'],
            'form.tujuan' => ['required', 'string', 'max:1000'],
            'form.rooms.*.room_id' => ['required', 'exists:rooms,id'],
            'form.items.*.item_id' => ['required', 'exists:items,id'],
            'form.items.*.jumlah' => ['required', 'integer', 'min:1'],
            'formFile' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:1024'],
        ], [
            'formFile.max' => 'Lampiran maksimal 1 MB.',
            'formFile.mimes' => 'Lampiran harus berupa PDF atau gambar.',
        ]);

        try {
            DB::transaction(function () {
                $borrowing = new Borrowing();
                $borrowing->kode_transaksi = $this->generateTransactionCode();
                $borrowing->user_id = $this->form['user_id'];
                $borrowing->approved_by = auth()->id();
                $borrowing->tujuan = $this->form['tujuan'];
                $borrowing->tanggal_mulai = $this->form['tanggal_mulai'];
                $borrowing->tanggal_selesai = $this->form['tanggal_selesai'];
                $borrowing->status = 'Disetujui';

                if ($this->formFile && Schema::hasColumn('borrowings', 'file_lampiran')) {
                    $borrowing->file_lampiran = $this->formFile->store('bukti-peminjaman', 'public');
                }

                $borrowing->save();

                foreach ($this->form['rooms'] as $room) {
                    $detail = new BorrowingDetail();
                    $detail->borrowing_id = $borrowing->id;
                    $detail->room_id = $room['room_id'];
                    $detail->item_id = null;
                    $detail->jumlah = 1;
                    $detail->status = 'Disetujui';
                    $detail->save();
                }

                foreach ($this->form['items'] as $item) {
                    $detail = new BorrowingDetail();
                    $detail->borrowing_id = $borrowing->id;
                    $detail->room_id = null;
                    $detail->item_id = $item['item_id'];
                    $detail->jumlah = (int) $item['jumlah'];
                    $detail->status = 'Disetujui';
                    $detail->save();
                }
            });

            $this->closeAddModal();
            $this->formFile = null;
            $this->dispatch('toast', type: 'success', message: 'Peminjaman berhasil dibuat dan otomatis disetujui.');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('form.user_id', 'Gagal menyimpan peminjaman: ' . $e->getMessage());
        }
    }

    protected function generateTransactionCode(): string
    {
        do {
            $code = 'TRX-' . now()->format('Ymd') . '-' . random_int(1000, 9999);
        } while (Borrowing::where('kode_transaksi', $code)->exists());

        return $code;
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'Menunggu' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
            'Disetujui' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
            'Ditolak' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
            'Dipinjam' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
            'Dikembalikan' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            'Selesai' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
            default => 'bg-gray-100 text-gray-600',
        };
    }
};
?>

<style>
    .scrollbar-hidden { scrollbar-width: none; -ms-overflow-style: none; }
    .scrollbar-hidden::-webkit-scrollbar { display: none; width: 0; height: 0; }
    .responsive-table { width: 100%; overflow-x: auto; overflow-y: visible; scrollbar-width: none; -ms-overflow-style: none; }
    .responsive-table::-webkit-scrollbar { display: none; width: 0; height: 0; }
    .responsive-table > table { min-width: 760px; }
    @media (max-width: 640px) { .responsive-table > table { min-width: 700px; } }
    .select2-container { width: 100% !important; }
    .select2-container--default .select2-selection--single { min-height: 44px; display: flex; align-items: center; border: 0; border-radius: .75rem; background: rgb(248 250 252); }
    .dark .select2-container--default .select2-selection--single { background: rgb(31 41 55); }
    .select2-container--default .select2-selection--single .select2-selection__rendered { padding-left: 1rem; font-size: .875rem; }
</style>

<div wire:poll.30s="reloadData(false)">
    {{-- HEADER --}}
    <div class="flex flex-col gap-4 mb-8 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Dashboard Peminjaman</h1>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Monitoring status ketersediaan & pengajuan peminjaman realtime.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2 p-1 bg-gray-100 rounded-2xl dark:bg-gray-800">
            <button wire:click="reloadData" class="px-4 py-2.5 text-xs font-bold text-slate-700 bg-white rounded-xl hover:bg-slate-50 shadow-sm dark:bg-gray-900 dark:text-slate-200">
                <i class="mr-1 fa-solid fa-rotate"></i> Reload
            </button>
            <button wire:click="openAddModal" class="px-5 py-2.5 text-xs font-bold text-white transition-all bg-emerald-600 rounded-xl hover:bg-emerald-700 shadow-md shadow-emerald-500/20">
                <i class="mr-1 fa-solid fa-plus"></i> Tambah 
            </button>
            <button wire:click="openScheduleModal" class="relative px-5 py-2.5 text-xs font-bold text-white transition-all bg-slate-800 rounded-xl hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">
                <i class="mr-1.5 fa-solid fa-calendar-days"></i> Info Jadwal
                @if($pendingScheduleCount > 0)
                    <span class="absolute -top-2 -right-2 min-w-5 h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center ring-2 ring-white dark:ring-gray-900">
                        {{ $pendingScheduleCount > 99 ? '99+' : $pendingScheduleCount }}
                    </span>
                @endif
            </button>
            <button wire:click="$set('tab', 'room')" class="px-5 py-2.5 text-xs font-bold rounded-xl transition-all {{ $tab === 'room' ? 'bg-white text-indigo-600 shadow-md dark:bg-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400' }}">
                <i class="mr-1.5 fa-solid fa-door-open"></i> Ruangan
            </button>
            <button wire:click="$set('tab', 'item')" class="px-5 py-2.5 text-xs font-bold rounded-xl transition-all {{ $tab === 'item' ? 'bg-white text-indigo-600 shadow-md dark:bg-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400' }}">
                <i class="mr-1.5 fa-solid fa-box-open"></i> Barang 
            </button>
        </div>
    </div>

    {{-- FILTER --}}
    <div class="p-6 mb-8 bg-white border border-gray-200 shadow-sm rounded-3xl dark:bg-gray-900 dark:border-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
            <div class="relative md:col-span-5">
                <i class="absolute left-4 top-1/2 z-10 -translate-y-1/2 text-gray-400 fa-solid fa-magnifying-glass"></i>
                <input type="text" wire:model.live.debounce.300ms="search" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pl-11 pr-4 text-sm font-medium text-slate-700 placeholder:text-slate-400 shadow-sm outline-none transition-all hover:border-slate-300 hover:bg-white focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500" placeholder="Cari {{ $tab === 'room' ? 'nama / kode ruangan' : 'nama / kode barang' }}...">
            </div>
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

    {{-- GRID --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($resources as $res)
            @php
                $name = $tab === 'room' ? $res->nama_ruangan : $res->nama_barang;
                $code = $tab === 'room' ? $res->kode_ruangan : $res->kode_barang;
                $capacity = $tab === 'room' ? $res->kapasitas . ' Orang' : $res->jumlah_total . ' Unit';
                $hasPending = $res->pending_count > 0;
            @endphp

            <div class="relative flex flex-col justify-between overflow-hidden transition-all bg-white border border-gray-200 shadow-sm rounded-3xl dark:bg-gray-900 dark:border-gray-800 hover:shadow-lg hover:border-indigo-200 dark:hover:border-indigo-900">
                <div class="p-6 border-b border-gray-100 dark:border-gray-800/60">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center min-w-0 gap-4">
                            <div class="flex items-center justify-center flex-shrink-0 text-indigo-600 rounded-2xl w-14 h-14 bg-indigo-50 dark:bg-indigo-900/30 dark:text-indigo-400">
                                <i class="text-2xl {{ $res->icon ?: ($tab === 'room' ? 'fa-solid fa-door-closed' : 'fa-solid fa-box') }}"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-bold text-gray-900 truncate text-md dark:text-white">{{ $name }}</h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $tab === 'room' ? 'Ruangan' : 'Barang' }}</span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 text-[10px] font-bold text-indigo-600 bg-indigo-50 rounded-md dark:bg-indigo-950 dark:text-indigo-300">#{{ $code }}</span>
                                </div>
                            </div>
                        </div>

                        <button wire:click="openDetailModal('{{ $tab }}', {{ $res->id }}, @js($name))" title="{{ $hasPending ? 'Ada pengajuan menunggu approval' : 'Info peminjaman' }}" class="relative flex flex-shrink-0 items-center justify-center rounded-xl w-10 h-10 {{ $hasPending ? 'bg-red-100 text-red-600 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300' : 'bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400' }}">
                            @if($hasPending)
                                <span class="absolute -top-1 -right-1 flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-red-500 text-white text-[9px] font-bold ring-2 ring-white dark:ring-gray-900">{{ $res->pending_count > 99 ? '99+' : $res->pending_count }}</span>
                            @endif
                            <i class="fa-solid {{ $hasPending ? 'fa-bell animate-pulse' : 'fa-circle-info' }} text-xs"></i>
                        </button>
                    </div>
                </div>

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
                        <span class="font-semibold text-gray-400 uppercase">Agenda Terdekat</span>
                        <span class="font-bold text-gray-700 dark:text-gray-200">
                            {{ $res->earliest_booking_date ? Carbon::parse($res->earliest_booking_date)->format('d M Y H:i') : '-' }}
                        </span>
                    </div>
                </div>

                <div class="p-4 border-t border-gray-100 bg-gray-50/50 rounded-b-3xl dark:border-gray-800/60 dark:bg-gray-800/20">
                    <button wire:click="openDetailModal('{{ $tab }}', {{ $res->id }}, @js($name))" class="flex items-center justify-center w-full gap-2 px-4 py-2.5 text-xs font-bold text-white transition-all bg-indigo-600 shadow-md rounded-2xl hover:bg-indigo-700 shadow-indigo-500/20">
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

    <div class="mt-8">{{ $resources->links() }}</div>

    {{-- MODAL INFO JADWAL --}}
    <section x-data="{ open: @entangle('isScheduleModalOpen') }">
        <template x-teleport="body">
            <div x-show="open" class="fixed inset-0 z-999 flex items-center justify-center p-4" x-cloak>
                <div class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm"></div>
                <div x-show="open" x-transition class="relative z-[101] w-full max-w-6xl max-h-[90vh] overflow-hidden bg-white shadow-2xl dark:bg-gray-900 rounded-3xl hide-scrollbar">
                    <div class="flex items-center justify-between gap-4 px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 dark:text-white"><i class="mr-2 text-indigo-500 fa-solid fa-calendar-days"></i> Info Jadwal Peminjaman</h4>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ Carbon::parse($dateFrom)->format('d M Y') }} - {{ Carbon::parse($dateTo)->format('d M Y') }}</p>
                        </div>
                        <button type="button" wire:click="closeScheduleModal" class="flex items-center justify-center w-9 h-9 text-gray-500 rounded-xl bg-gray-100 hover:text-red-500 dark:bg-gray-800">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                        <div class="relative">
                            <i class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 fa-solid fa-magnifying-glass"></i>
                            <input type="text" wire:model.live.debounce.300ms="scheduleSearch" placeholder="Cari kode transaksi, nama peminjam, atau tujuan..." class="w-full py-3 pl-11 pr-4 text-sm border border-slate-200 rounded-xl bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="overflow-x-auto overflow-y-auto scrollbar-hidden max-h-[58vh]">
                        @php($scheduleRows = $this->scheduleQuery()->paginate(10, ['*'], 'schedulePage'))
                        <table class="w-full min-w-[760px] text-xs text-left">
                            <thead class="sticky top-0 z-10 bg-slate-50 dark:bg-slate-800">
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 font-bold uppercase text-slate-400">Kode Trx</th>
                                    <th class="px-4 py-3 font-bold uppercase text-slate-400">Nama Peminjam</th>
                                    <th class="px-4 py-3 font-bold uppercase text-slate-400">Tujuan</th>
                                    <th class="px-4 py-3 font-bold uppercase text-slate-400">Waktu</th>
                                    <th class="px-4 py-3 font-bold text-center uppercase text-slate-400">Status</th>
                                    <th class="px-4 py-3 font-bold text-center uppercase text-slate-400">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($scheduleRows as $booking)
                                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                        <td class="px-4 py-4 font-bold text-indigo-600 dark:text-indigo-400">{{ $booking->kode_transaksi }}</td>
                                        <td class="px-4 py-4 font-semibold text-gray-900 dark:text-white">{{ $booking->user?->name ?? '-' }}</td>
                                        <td class="max-w-xs px-4 py-4 text-gray-600 dark:text-gray-300">{{ $booking->tujuan }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="font-semibold text-gray-900 dark:text-white">{{ optional($booking->tanggal_mulai)->format('d M Y H:i') }}</div>
                                            <div class="text-[10px] text-gray-400">s/d {{ optional($booking->tanggal_selesai)->format('d M Y H:i') }}</div>
                                        </td>
                                        <td class="px-4 py-4 text-center"><span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold {{ $this->statusBadgeClass($booking->status) }}">{{ $booking->status }}</span></td>
                                        <td class="px-4 py-4 text-center">
                                            <div class="flex flex-wrap items-center justify-center gap-2">
                                <button wire:click="openBorrowingDetailModal({{ $booking->id }})" class="px-3 py-2 text-[10px] font-bold text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200">
                                    <i class="mr-1 fa-solid fa-eye"></i>Detail
                                </button>
                                    @if(in_array($booking->status, ['Menunggu', 'Disetujui'], true))
                                        <button wire:click="openApprovalModal({{ $booking->id }})" class="px-3 py-2 text-[10px] font-bold text-white rounded-lg {{ $booking->status === 'Menunggu' ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-slate-600 hover:bg-slate-700' }}">
                                            <i class="mr-1 fa-solid {{ $booking->status === 'Menunggu' ? 'fa-gavel' : 'fa-pen-to-square' }}"></i>{{ $booking->status === 'Menunggu' ? 'Approve' : 'Tindak Lanjut' }}
                                        </button>
                                    @else
                                        <button wire:click="openBorrowingDetailModal({{ $booking->id }})" class="px-3 py-2 text-[10px] font-bold text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200">
                                            <i class="mr-1 fa-solid fa-eye"></i>Detail
                                        </button>
                                    @endif
                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">Tidak ada data peminjaman pada rentang tanggal ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800">
                        {{ $scheduleRows->links() }}
                    </div>
                </div>
            </div>
        </template>
    </section>

    {{-- MODAL DETAIL RESOURCE --}}
    <section x-data="{ open: @entangle('isDetailModalOpen') }">
        <template x-teleport="body">
            <div x-show="open" class="fixed inset-0 z-9991 flex items-center justify-center p-4" x-cloak>
                <div class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm"></div>
                <div x-show="open" x-transition class="relative z-[111] w-full max-w-5xl max-h-[90vh] overflow-hidden bg-white shadow-2xl dark:bg-gray-900 rounded-3xl hide-scrollbar">
                    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 dark:text-white">Data Peminjaman</h4>
                            <p class="text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ $selectedResourceName }}</p>
                        </div>
                        <button wire:click="closeDetailModal" class="flex items-center justify-center w-9 h-9 text-gray-500 rounded-xl bg-gray-100 hover:text-red-500 dark:bg-gray-800"><i class="fa-solid fa-xmark"></i></button>
                    </div>

                    <div class="overflow-x-auto overflow-y-auto scrollbar-hidden max-h-[72vh]">
                        <table class="w-full min-w-[760px] text-xs text-left">
                            <thead class="sticky top-0 z-10 bg-slate-50 dark:bg-slate-800">
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="p-3 font-bold uppercase text-slate-400">Kode Trx</th>
                                    <th class="p-3 font-bold uppercase text-slate-400">Peminjam</th>
                                    <th class="p-3 font-bold uppercase text-slate-400">Tujuan</th>
                                    <th class="p-3 font-bold uppercase text-slate-400">Waktu</th>
                                    <th class="p-3 font-bold text-center uppercase text-slate-400">Status</th>
                                    <th class="p-3 font-bold text-center uppercase text-slate-400">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($activeBorrowings as $item)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                        <td class="p-3 font-bold text-indigo-600 dark:text-indigo-400">{{ $item['kode_transaksi'] }}</td>
                                        <td class="p-3">
                                            <div class="font-bold text-gray-900 dark:text-white">{{ $item['peminjam'] }}</div>
                                            <div class="text-[10px] text-gray-400">{{ $item['no_hp'] }}</div>
                                        </td>
                                        <td class="p-3 text-gray-600 dark:text-gray-300">{{ $item['tujuan'] }}</td>
                                        <td class="p-3 whitespace-nowrap">
                                            <div class="font-semibold text-gray-900 dark:text-white">{{ $item['tanggal_mulai'] }}</div>
                                            <div class="text-[10px] text-gray-400">s/d {{ $item['tanggal_selesai'] }}</div>
                                        </td>
                                        <td class="p-3 text-center"><span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold {{ $this->statusBadgeClass($item['status']) }}">{{ $item['status'] }}</span></td>
                                        <td class="p-3 text-center">
                            @if($item['status'] === 'Menunggu')
                                <button wire:click="openApprovalModal({{ $item['borrowing_id'] }})" class="px-3 py-2 text-[10px] font-bold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                    <i class="mr-1 fa-solid fa-gavel"></i> Approve
                                </button>
                            @elseif($item['status'] === 'Disetujui')
                                <button wire:click="openApprovalModal({{ $item['borrowing_id'] }})" class="px-3 py-2 text-[10px] font-bold text-white bg-slate-600 rounded-lg hover:bg-slate-700">
                                    <i class="mr-1 fa-solid fa-pen-to-square"></i> Tindak Lanjut
                                </button>
                            @else
                                <button wire:click="openBorrowingDetailModal({{ $item['borrowing_id'] }})" class="px-3 py-2 text-[10px] font-bold text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200">
                                    <i class="mr-1 fa-solid fa-eye"></i> Detail
                                </button>
                            @endif
                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="py-12 text-center text-gray-400">Tidak ada agenda peminjaman aktif pada rentang tanggal ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end px-6 py-4 border-t border-gray-100 dark:border-gray-800">
                        <button type="button" wire:click="closeDetailModal" class="px-5 py-2.5 text-sm font-bold text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300">Close</button>
                    </div>
                </div>
            </div>
        </template>
    </section>

    {{-- MODAL DETAIL TRANSAKSI --}}
    <section x-data="{ open: @entangle('isBorrowingDetailModalOpen') }">
        <template x-teleport="body">
            <div x-show="open" class="fixed inset-0 z-9992 flex items-center justify-center p-4" x-cloak>
                <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm"></div>
                <div x-show="open" x-transition class="relative z-[151] w-full max-w-3xl max-h-[90vh] overflow-y-auto scrollbar-hidden bg-white dark:bg-gray-900 rounded-3xl shadow-2xl hide-scrollbar">
                    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                        <div>
                            <h4 class="text-lg font-bold text-gray-900 dark:text-white">Detail Peminjaman</h4>
                            <p class="mt-1 text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ $selectedBorrowing['kode_transaksi'] ?? '-' }}</p>
                        </div>
                        <button type="button" wire:click="closeBorrowingDetailModal" class="flex items-center justify-center w-9 h-9 text-gray-500 rounded-xl bg-gray-100 hover:text-red-500 dark:bg-gray-800"><i class="fa-solid fa-xmark"></i></button>
                    </div>

                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800"><div class="text-[10px] font-bold uppercase text-slate-400">Nama Peminjam</div><div class="mt-1 font-bold text-gray-900 dark:text-white">{{ $selectedBorrowing['nama'] ?? '-' }}</div></div>
                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800"><div class="text-[10px] font-bold uppercase text-slate-400">No. HP</div><div class="mt-1 font-bold text-gray-900 dark:text-white">{{ $selectedBorrowing['no_hp'] ?? '-' }}</div></div>
                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800"><div class="text-[10px] font-bold uppercase text-slate-400">Waktu</div><div class="mt-1 font-bold text-gray-900 dark:text-white">{{ $selectedBorrowing['tanggal_mulai'] ?? '-' }}</div><div class="text-[10px] text-slate-400">s/d {{ $selectedBorrowing['tanggal_selesai'] ?? '-' }}</div></div>
                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800"><div class="text-[10px] font-bold uppercase text-slate-400">Status</div><div class="mt-2"><span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold {{ $this->statusBadgeClass($selectedBorrowing['status'] ?? '') }}">{{ $selectedBorrowing['status'] ?? '-' }}</span></div></div>
                        </div>

                        <div><div class="mb-2 text-xs font-bold text-gray-700 dark:text-gray-300">Tujuan / Keperluan</div><div class="p-4 text-sm text-gray-700 rounded-2xl bg-slate-50 dark:bg-slate-800 dark:text-gray-300">{{ $selectedBorrowing['tujuan'] ?? '-' }}</div></div>
                        <div class="flex items-center justify-between gap-3 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800">
                            <div>
                                <div class="text-[10px] font-bold uppercase text-slate-400">Lampiran / File SP</div>
                                <div class="mt-1 text-xs font-semibold text-slate-700 dark:text-slate-200">{{ !empty($selectedBorrowing['file_lampiran'] ?? null) ? 'Tersedia' : '-' }}</div>
                            </div>
                            @if(!empty($selectedBorrowing['file_lampiran'] ?? null))
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($selectedBorrowing['file_lampiran'] ?? '') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-3 py-2 text-xs font-bold text-indigo-700 bg-indigo-100 rounded-xl hover:bg-indigo-200">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Lihat Lampiran
                                </a>
                            @endif
                        </div>

                        <div>
                            <div class="mb-3 text-xs font-bold text-gray-700 dark:text-gray-300">Ruangan / Barang yang Dipinjam</div>
                            <div class="overflow-hidden border rounded-2xl border-slate-200 dark:border-gray-800">
                                <table class="w-full min-w-[760px] text-xs text-left">
                                    <thead class="bg-slate-50 dark:bg-slate-800"><tr><th class="p-3 text-slate-400">Tipe</th><th class="p-3 text-slate-400">Nama</th><th class="p-3 text-slate-400">Kode</th><th class="p-3 text-center text-slate-400">Jumlah</th><th class="p-3 text-center text-slate-400">Status</th><th class="p-3 text-center text-slate-400">Bukti Pengembalian</th></tr></thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-gray-800">
                                        @forelse(($selectedBorrowing['details'] ?? []) as $detail)
                                            <tr>
                                                <td class="p-3">{{ $detail['type'] }}</td>
                                                <td class="p-3 font-semibold">{{ $detail['name'] }}</td>
                                                <td class="p-3">#{{ $detail['code'] }}</td>
                                                <td class="p-3 text-center">{{ $detail['jumlah'] }}</td>
                                                <td class="p-3 text-center"><span class="inline-flex px-2 py-1 rounded-full text-[9px] font-bold {{ $this->statusBadgeClass($detail['status']) }}">{{ $detail['status'] }}</span></td>
                                                <td class="p-3 text-center">
                                                    @if(!empty($detail['file_bukti_pengembalian']))
                                                        <a href="{{ \Illuminate\Support\Facades\Storage::url($detail['file_bukti_pengembalian']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 px-2 py-1.5 text-[10px] font-bold text-indigo-700 bg-indigo-100 rounded-lg hover:bg-indigo-200">
                                                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka
                                                        </a>
                                                    @else
                                                        <span class="text-slate-400">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="p-6 text-center text-slate-400">Tidak ada detail fasilitas.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @if(!empty($selectedBorrowing['catatan_admin']))
                            <div><div class="mb-2 text-xs font-bold text-gray-700 dark:text-gray-300">Catatan Admin</div><div class="p-4 text-sm text-gray-700 rounded-2xl bg-amber-50 dark:bg-amber-900/20 dark:text-amber-200">{{ $selectedBorrowing['catatan_admin'] }}</div></div>
                        @endif

                        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                            <button type="button" wire:click="closeBorrowingDetailModal" class="px-5 py-2.5 text-sm font-bold text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300">Close</button>
                            @if(($selectedBorrowing['status'] ?? '') === 'Menunggu')
                                <button type="button" wire:click="openApprovalModal({{ $selectedBorrowing['id'] ?? 0 }})" class="px-5 py-2.5 text-sm font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700">
                                    <i class="mr-1 fa-solid fa-gavel"></i>Approve
                                </button>
                            @elseif(($selectedBorrowing['status'] ?? '') === 'Disetujui')
                                <button type="button" wire:click="openApprovalModal({{ $selectedBorrowing['id'] ?? 0 }})" class="px-5 py-2.5 text-sm font-bold text-white bg-slate-600 rounded-xl hover:bg-slate-700">
                                    <i class="mr-1 fa-solid fa-pen-to-square"></i>Tindak Lanjut
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </section>

    {{-- MODAL APPROVAL REUSABLE --}}
    <section x-data="{ open: @entangle('isApprovalModalOpen') }">
        <template x-teleport="body">
            <div x-show="open" class="fixed inset-0 z-9993 flex items-center justify-center p-4" x-cloak>
                <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm"></div>
                <div x-show="open" x-transition class=" relative z-[201] w-full max-w-5xl max-h-[92vh] overflow-y-auto scrollbar-hidden bg-white dark:bg-gray-900 rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-800 hide-scrollbar">
                    <div class="sticky top-0 z-20 flex items-center justify-between px-6 py-5 bg-white/95 border-b border-gray-100 dark:bg-gray-900/95 dark:border-gray-800 backdrop-blur">
                        <div>
                            <h4 class="text-lg font-bold text-gray-900 dark:text-white">{{ $approvalCurrentStatus === 'Menunggu' ? 'Approve Pengajuan' : 'Tindak Lanjut Peminjaman' }}</h4>
                            <p class="mt-1 text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ $approvalTransactionCode }}</p>
                        </div>
                        <button type="button" wire:click="closeApprovalModal" class="flex items-center justify-center w-9 h-9 text-gray-500 rounded-xl bg-gray-100 hover:text-red-500 dark:bg-gray-800"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <form wire:submit="saveApproval" class="p-6 space-y-6">
                        @php($selectedApproval = !empty($approvalBorrowingId) ? \App\Models\Borrowing::with('user')->find($approvalBorrowingId) : null)
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800"><div class="text-[10px] font-bold uppercase text-slate-400">Nama Peminjam</div><div class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $selectedApproval?->user?->name ?? '-' }}</div></div>
                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800"><div class="text-[10px] font-bold uppercase text-slate-400">No. HP</div><div class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $selectedApproval?->user?->no_hp ?? $selectedApproval?->user?->no_wa ?? '-' }}</div></div>
                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800"><div class="text-[10px] font-bold uppercase text-slate-400">Waktu</div><div class="mt-1 text-xs font-bold text-gray-900 dark:text-white">{{ optional($selectedApproval?->tanggal_mulai)->format('d M Y H:i') }}</div><div class="text-[10px] text-slate-400">s/d {{ optional($selectedApproval?->tanggal_selesai)->format('d M Y H:i') }}</div></div>
                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800"><div class="text-[10px] font-bold uppercase text-slate-400">Lampiran</div>@if($selectedApproval?->getAttribute('file_lampiran'))<a href="{{ \Illuminate\Support\Facades\Storage::url($selectedApproval->getAttribute('file_lampiran')) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 mt-2 text-xs font-bold text-indigo-600 hover:text-indigo-700"><i class="fa-solid fa-arrow-up-right-from-square"></i> Lihat Lampiran</a>@else<div class="mt-1 text-xs text-slate-400">-</div>@endif</div>
                        </div>

                        <div>
                            <div class="mb-3 text-xs font-bold text-gray-700 dark:text-gray-300">Rincian Peminjaman</div>
                            <div class="responsive-table border rounded-2xl border-slate-200 dark:border-gray-800">
                                <table class="w-full min-w-[760px] text-xs text-left">
                                    <thead class="bg-slate-50 dark:bg-gray-800"><tr><th class="p-3 font-bold uppercase text-slate-400">Tipe</th><th class="p-3 font-bold uppercase text-slate-400">Kode</th><th class="p-3 font-bold uppercase text-slate-400">Nama</th><th class="p-3 font-bold text-center uppercase text-slate-400">Jumlah</th><th class="p-3 font-bold text-center uppercase text-slate-400">Status</th><th class="p-3 font-bold text-center uppercase text-slate-400">Bukti Pengembalian</th></tr></thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-gray-800">
                                        @forelse($approvalDetails as $index => $detail)
                                            <tr>
                                                <td class="p-3">{{ $detail['type'] }}</td><td class="p-3 font-semibold">#{{ $detail['code'] }}</td><td class="p-3 font-semibold">{{ $detail['name'] }}</td>
                                                <td class="p-3 text-center">@if($approvalCurrentStatus === 'Menunggu' || (($detail['status_original'] ?? $detail['status']) === 'Disetujui' && $approvalCurrentStatus === 'Disetujui')) @if($detail['type'] === 'Barang')<input type="number" min="1" wire:model="approvalDetails.{{ $index }}.jumlah" class="w-20 px-2 py-1.5 text-xs text-center border rounded-lg bg-slate-50 border-slate-200 dark:bg-gray-800 dark:border-gray-700 dark:text-white">@else{{ $detail['jumlah'] }}@endif @else{{ $detail['jumlah'] }}@endif</td>
                                                <td class="p-3 text-center">
                                                    @if($approvalCurrentStatus === 'Menunggu' && ($detail['status_original'] ?? $detail['status']) === 'Menunggu')
                                                        <select wire:model.live="approvalDetails.{{ $index }}.status" class="px-2 py-1.5 text-xs border rounded-lg bg-white border-slate-200 dark:bg-gray-800 dark:border-gray-700 dark:text-white"><option value="Menunggu">Menunggu</option><option value="Disetujui">Disetujui</option><option value="Ditolak">Ditolak</option></select>
                                                    @elseif($approvalCurrentStatus === 'Disetujui' && ($detail['status_original'] ?? $detail['status']) === 'Disetujui')
                                                        <select wire:model.live="approvalDetails.{{ $index }}.status" class="px-2 py-1.5 text-xs border rounded-lg bg-white border-slate-200 dark:bg-gray-800 dark:border-gray-700 dark:text-white"><option value="Disetujui">Disetujui</option><option value="Dikembalikan">Dikembalikan</option></select>
                                                    @else
                                                        <span class="inline-flex px-2 py-1 rounded-full text-[9px] font-bold {{ $this->statusBadgeClass($detail['status']) }}">{{ $detail['status'] }}</span>
                                                    @endif
                                                </td>
                                                <td class="p-3 text-center">@if(!empty($detail['file_bukti_pengembalian']))<a href="{{ \Illuminate\Support\Facades\Storage::url($detail['file_bukti_pengembalian']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 px-2 py-1.5 text-[10px] font-bold text-indigo-700 bg-indigo-100 rounded-lg hover:bg-indigo-200"><i class="fa-solid fa-arrow-up-right-from-square"></i>Buka</a>@else<span class="text-slate-400">-</span>@endif</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="p-8 text-center text-slate-400">Tidak ada detail peminjaman.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div><label class="block mb-2 text-xs font-bold text-gray-700 dark:text-gray-300">Status Utama</label><select wire:model.live="approvalStatus" class="w-full px-4 py-3 text-sm border border-gray-200 rounded-xl bg-white dark:bg-gray-800 dark:border-gray-700 dark:text-white focus:ring-2 focus:ring-indigo-500">@foreach($this->approvalStatusOptions() as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select>@error('approvalStatus')<span class="block mt-1 text-xs text-red-500">{{ $message }}</span>@enderror</div>
                            <div><label class="block mb-2 text-xs font-bold text-gray-700 dark:text-gray-300">Catatan Admin</label><textarea wire:model="catatan_admin" rows="3" placeholder="Tambahkan catatan peminjaman/approval..." class="w-full px-4 py-3 text-sm border border-gray-200 resize-none rounded-xl bg-slate-50 dark:bg-gray-800 dark:border-gray-700 dark:text-white focus:ring-2 focus:ring-indigo-500"></textarea>@error('catatan_admin')<span class="block mt-1 text-xs text-red-500">{{ $message }}</span>@enderror</div>
                        </div>

                        <div><label class="block mb-2 text-xs font-bold text-gray-700 dark:text-gray-300">Lampiran / Bukti Pendukung <span class="font-normal text-slate-400">(Opsional, max. 1 MB)</span></label><input type="file" wire:model="approvalFile" accept="application/pdf,image/jpeg,image/png,image/webp" class="block w-full text-xs text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-bold file:text-indigo-700 hover:file:bg-indigo-100"><div wire:loading wire:target="approvalFile" class="flex items-center gap-2 mt-2 text-xs font-medium text-indigo-600"><i class="fa-solid fa-spinner animate-spin"></i>Mengunggah file...</div>@if($approvalFile)<div class="flex items-center gap-2 p-3 mt-2 text-xs rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300"><i class="fa-solid fa-paperclip"></i><span class="truncate">{{ $approvalFile->getClientOriginalName() }}</span></div>@endif @error('approvalFile')<span class="block mt-1 text-xs text-red-500">{{ $message }}</span>@enderror</div>

                        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                            <button type="button" wire:click="closeApprovalModal" class="px-5 py-2.5 text-sm font-bold text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300">Tutup</button>
                            <button type="submit" class="px-5 py-2.5 text-sm font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 disabled:opacity-50" wire:loading.attr="disabled" wire:target="saveApproval"><span wire:loading.remove wire:target="saveApproval"><i class="mr-1 fa-solid fa-check"></i>{{ $approvalCurrentStatus === 'Menunggu' ? 'Approve' : 'Simpan Tindak Lanjut' }}</span><span wire:loading wire:target="saveApproval"><i class="mr-1 fa-solid fa-spinner animate-spin"></i>Sedang menyimpan data...</span></button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </section>

    {{-- MODAL TAMBAH BOOKING --}}
    <section x-data="{ open: @entangle('isAddModalOpen') }">
        <template x-teleport="body">
            <div x-show="open" class="fixed inset-0 z-9994 flex items-center justify-center p-4" x-cloak>
                <div class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm"></div>
                <div x-show="open" x-transition class="relative z-[81] w-full max-w-5xl max-h-[90vh] overflow-y-auto scrollbar-hidden bg-white dark:bg-gray-900 rounded-3xl shadow-2xl hide-scrollbar">
                    <div class="sticky top-0 z-10 flex items-center justify-between px-6 py-5 bg-white/95 border-b border-gray-100 dark:bg-gray-900/95 dark:border-gray-800 backdrop-blur">
                        <h4 class="text-xl font-bold text-gray-900 dark:text-white">Tambah Booking</h4>
                        <button type="button" wire:click="closeAddModal" class="flex items-center justify-center w-9 h-9 text-gray-500 rounded-xl bg-gray-100 hover:text-red-500 dark:bg-gray-800"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <form wire:submit="saveBooking" class="p-6 space-y-6">
                        @if($errors->has('form.user_id'))
                            <div class="p-3 text-xs font-medium text-red-700 border border-red-200 rounded-xl bg-red-50 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800">
                                {{ $errors->first('form.user_id') }}
                            </div>
                        @endif
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="md:col-span-3">
                                <label class="block mb-2 text-xs font-bold text-gray-700 dark:text-gray-300">Peminjam / User</label>
                                <div wire:ignore x-data="{
                                    init() {
                                        const el = $(this.$refs.selectUser).select2({
                                            placeholder: '-- Pilih Peminjam --',
                                            width: '100%',
                                            dropdownParent: $(this.$el)
                                        });
                                        el.on('change', e => $wire.set('form.user_id', e.target.value));
                                        $watch('$wire.form.user_id', value => el.val(value).trigger('change.select2'));
                                    }
                                }">
                                    <select x-ref="selectUser" class="w-full px-4 py-3 text-sm bg-slate-50 border-none rounded-xl dark:bg-gray-800 dark:text-white">
                                        <option value="">-- Pilih Peminjam --</option>
                                        @foreach($usersList as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('form.user_id') <span class="block mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div><label class="block mb-2 text-xs font-bold text-gray-700 dark:text-gray-300">Mulai</label><input type="datetime-local" wire:model="form.tanggal_mulai" class="w-full px-4 py-3 text-sm border-none bg-slate-50 rounded-xl dark:bg-gray-800 dark:text-white"></div>
                            <div><label class="block mb-2 text-xs font-bold text-gray-700 dark:text-gray-300">Selesai</label><input type="datetime-local" wire:model="form.tanggal_selesai" class="w-full px-4 py-3 text-sm border-none bg-slate-50 rounded-xl dark:bg-gray-800 dark:text-white"></div>
                            <div><label class="block mb-2 text-xs font-bold text-gray-700 dark:text-gray-300">Tujuan</label><input type="text" wire:model="form.tujuan" class="w-full px-4 py-3 text-sm border-none bg-slate-50 rounded-xl dark:bg-gray-800 dark:text-white"></div>
                        </div>

                        <div class="p-5 border rounded-2xl border-slate-200 dark:border-gray-800">
                            <div class="flex items-center justify-between mb-3"><h5 class="font-bold text-gray-900 dark:text-white">Ruangan</h5><button type="button" wire:click="addRoomRow" class="px-3 py-2 text-xs font-bold text-indigo-600 rounded-lg bg-indigo-50">+ Tambah Ruangan</button></div>
                            <div class="space-y-3">
                                @foreach($form['rooms'] as $index => $room)
                                    <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center" wire:key="admin-room-{{ $index }}">
                                        <div wire:ignore wire:key="select-room-wrapper-{{ $index }}" class="flex-1" x-data="{
                                            init() {
                                                const el = $(this.$refs.selectRoom).select2({
                                                    placeholder: '-- Pilih Ruangan --',
                                                    width: '100%',
                                                    dropdownParent: $(this.$el)
                                                });
                                                el.on('change', e => $wire.set('form.rooms.{{ $index }}.room_id', e.target.value));
                                                $watch('$wire.form.rooms[{{ $index }}].room_id', value => el.val(value).trigger('change.select2'));
                                            }
                                        }">
                                            <select x-ref="selectRoom" class="w-full px-4 py-3 text-sm border-none bg-slate-50 rounded-xl dark:bg-gray-800 dark:text-white">
                                                <option value="">-- Pilih Ruangan --</option>
                                                @foreach($roomsList as $r)
                                                    <option value="{{ $r->id }}">{{ $r->nama_ruangan }} (#{{ $r->kode_ruangan }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="button" wire:click="removeRoomRow({{ $index }})" class="p-3 text-red-500 bg-red-50 rounded-xl dark:bg-red-500/10"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="p-5 border rounded-2xl border-slate-200 dark:border-gray-800">
                            <div class="flex items-center justify-between mb-3"><h5 class="font-bold text-gray-900 dark:text-white">Barang / Inventaris</h5><button type="button" wire:click="addItemRow" class="px-3 py-2 text-xs font-bold text-indigo-600 rounded-lg bg-indigo-50">+ Tambah Barang</button></div>
                            <div class="space-y-3">
                                @foreach($form['items'] as $index => $item)
                                    <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center" wire:key="admin-item-{{ $index }}">
                                        <div wire:ignore wire:key="select-item-wrapper-{{ $index }}" class="flex-1" x-data="{
                                            init() {
                                                const el = $(this.$refs.selectItem).select2({
                                                    placeholder: '-- Pilih Barang --',
                                                    width: '100%',
                                                    dropdownParent: $(this.$el)
                                                });
                                                el.on('change', e => $wire.set('form.items.{{ $index }}.item_id', e.target.value));
                                                $watch('$wire.form.items[{{ $index }}].item_id', value => el.val(value).trigger('change.select2'));
                                            }
                                        }">
                                            <select x-ref="selectItem" class="w-full px-4 py-3 text-sm border-none bg-slate-50 rounded-xl dark:bg-gray-800 dark:text-white">
                                                <option value="">-- Pilih Barang --</option>
                                                @foreach($itemsList as $it)
                                                    <option value="{{ $it->id }}">{{ $it->nama_barang }} (#{{ $it->kode_barang }}) - Stok {{ $it->jumlah_total }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @php($selectedStock = $this->itemStock($item['item_id'] ?? null))
                                        <div class="w-28 shrink-0">
                                            <input type="number" min="{{ $selectedStock > 0 ? 1 : 0 }}" max="{{ $selectedStock }}" {{ !$item['item_id'] ? 'disabled' : '' }} wire:model.live="form.items.{{ $index }}.jumlah" class="w-full px-3 py-3 text-sm border-none bg-slate-50 rounded-xl dark:bg-gray-800 dark:text-white disabled:opacity-50 disabled:cursor-not-allowed" placeholder="Qty">
                                            <div class="mt-1 text-[9px] text-slate-400 text-center">{{ $selectedStock > 0 ? 'Maks. '.$selectedStock : 'Pilih barang' }}</div>
                                            @error('form.items.'.$index.'.jumlah') <span class="block mt-1 text-[10px] text-red-500">{{ $message }}</span> @enderror
                                        </div>
                                        <button type="button" wire:click="removeItemRow({{ $index }})" class="p-3 text-red-500 bg-red-50 rounded-xl dark:bg-red-500/10"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="block mb-2 text-xs font-bold text-gray-700 dark:text-gray-300">Lampiran (File SP) (Opsional)</label>
                            <input type="file" wire:model="formFile" accept="application/pdf,image/jpeg,image/png,image/webp" class="block w-full text-xs text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-bold file:text-indigo-700 hover:file:bg-indigo-100">
                            <div wire:loading wire:target="formFile" class="flex items-center gap-2 mt-2 text-xs font-medium text-indigo-600"><i class="fa-solid fa-spinner animate-spin"></i> Mengunggah file...</div>
                            @if($formFile)
                                <div class="flex items-center gap-2 p-3 mt-2 text-xs rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300">
                                    <i class="fa-solid fa-paperclip"></i><span class="truncate">{{ $formFile->getClientOriginalName() }}</span>
                                </div>
                            @endif
                            @error('formFile') <span class="block mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                            <button type="button" wire:click="closeAddModal" class="px-5 py-2.5 text-sm font-bold text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300">Batal</button>
                            <button type="submit" class="px-5 py-2.5 text-sm font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700" wire:loading.attr="disabled" wire:target="saveBooking"><span wire:loading.remove wire:target="saveBooking">Simpan Booking</span><span wire:loading wire:target="saveBooking"><i class="mr-1 fa-solid fa-spinner animate-spin"></i>Sedang menyimpan data...</span></button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </section>

    <x-toast />
</div>
