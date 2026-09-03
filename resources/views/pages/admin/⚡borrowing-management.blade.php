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
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

new #[Layout('layouts.app')] #[Title('Dashboard Peminjaman Grid')] class extends Component
{
    use WithPagination, WithFileUploads;
    public string $search = '';
    public string $dateFrom;
    public string $dateTo;
    public string $tab = 'room';
    public bool $isScheduleModalOpen = false;
    public string $scheduleSearch = '';
    public bool $isBorrowingDetailModalOpen = false;
    public array $selectedBorrowing = [];
    public bool $isDetailModalOpen = false;
    public ?int $selectedResourceId = null;
    public string $selectedResourceName = '';
    public string $selectedResourceType = 'room';
    public array $activeBorrowings = [];
    public bool $isAddModalOpen = false;
    public array $form = [
        'user_id' => '',
        'tanggal_mulai' => '',
        'tanggal_selesai' => '',
        'tujuan' => '',
        'rooms' => [ ['room_id' => ''] ],
        'items' => [ ['item_id' => '', 'jumlah' => 0] ],
    ];
    public bool $isApprovalModalOpen = false;
    public ?int $approvalBorrowingId = null;
    public string $approvalTransactionCode = '';
    public string $approvalCurrentStatus = 'Menunggu';
    public string $approvalStatus = 'Menunggu';
    public string $catatan = '';
    public string $catatan_admin = '';
    public array $returnUploads = [];
    public $formFile = null;
    public $editFile = null;
    public array $approvalDetails = [];
    public bool $isEditModalOpen = false;
    public ?int $editBorrowingId = null;
    public array $editForm = [
        'user_id' => '',
        'tanggal_mulai' => '',
        'tanggal_selesai' => '',
        'tujuan' => '',
        'status' => 'Menunggu',
        'catatan' => '',
        'catatan_admin' => '',
        'catatan_pengembalian' => '',
    ];
    public array $editDetails = [];

    public array $statusOptions = ['Menunggu', 'Disetujui', 'Ditolak', 'Dipinjam', 'Dikembalikan', 'Selesai'];

    public function mount(): void
    {
        $this->dateFrom = '';
        $this->dateTo = '';
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingScheduleSearch(): void { $this->resetPage('schedulePage'); }
    public function updatingDateFrom(): void { $this->resetPage(); $this->refreshOpenDetail(); }
    public function updatingDateTo(): void { $this->resetPage(); $this->refreshOpenDetail(); }
    public function updatingTab(): void { $this->resetPage(); }

    protected function dateRange(): array
    {
        if (blank($this->dateFrom)) {
            return [null, null];
        }

        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = blank($this->dateTo)
            ? null
            : Carbon::parse($this->dateTo)->endOfDay();

        if ($to && $from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    protected function applyDateOverlap($query, string $startColumn = 'tanggal_mulai', string $endColumn = 'tanggal_selesai')
    {
        [$startDate, $endDate] = $this->dateRange();

        if (!$startDate) {
            return $query;
        }

        $query->where($startColumn, '>=', $startDate);
        if ($endDate) {
            $query->where($startColumn, '<=', $endDate);
        }

        return $query;
    }

    protected function borrowingNote(Borrowing $borrowing): string
    {
        return (string) ($borrowing->catatan ?? '');
    }

    protected function borrowingReturnNote(Borrowing $borrowing): string
    {
        return $borrowing->details->pluck('catatan_pengembalian')->filter()->implode("
");
    }

    protected function borrowingReturnProof(Borrowing $borrowing): ?string
    {
        return $borrowing->details->pluck('file_bukti_pengembalian')->filter()->first() ?: null;
    }

    protected function normalizeFacilities($value): array
    {
        if (is_array($value)) {
            return collect($value)->map(fn ($item) => trim((string) $item))->filter()->unique()->values()->all();
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return collect($decoded)->map(fn ($item) => trim((string) $item))->filter()->unique()->values()->all();
            }

            return collect(preg_split('/[,;\n]+/', $value))->map(fn ($item) => trim($item))->filter()->unique()->values()->all();
        }

        return [];
    }

    protected function resourceFacilities($resource): array
    {
        if (!$resource) {
            return [];
        }

        foreach (['available_fasilitas', 'fasilitas'] as $column) {
            $facilities = $this->normalizeFacilities($resource->getAttribute($column));

            if ($facilities) {
                return $facilities;
            }
        }

        return [];
    }

    protected function detailFacilities(BorrowingDetail $detail): array
    {
        $selected = $this->normalizeFacilities($detail->getAttribute('fasilitas'));
        $resource = $detail->room ?: $detail->item;
        $available = $this->resourceFacilities($resource);

        return [
            'available' => $available,
            'selected' => $selected ?: $available,
        ];
    }

    public function facilityIcon(string $facility): string
    {
        $facility = strtolower($facility);

        return match (true) {
            // Pendingin ruangan
            str_contains($facility, 'ac') ||
            str_contains($facility, 'pendingin') ||
            str_contains($facility, 'air conditioner')
                => 'fa-snowflake',

            // Proyektor / presentasi
            str_contains($facility, 'proyektor') ||
            str_contains($facility, 'projector')
                => 'fa-video',

            // Interactive Flat Panel / Smart Display
            str_contains($facility, 'interactive flat panel') ||
            str_contains($facility, 'smart display') ||
            str_contains($facility, 'smart tv')
                => 'fa-display',

            // Komputer
            str_contains($facility, 'komputer') ||
            str_contains($facility, 'computer') ||
            str_contains($facility, 'pc') ||
            str_contains($facility, 'laptop')
                => 'fa-computer',

            // Internet / jaringan
            str_contains($facility, 'internet') ||
            str_contains($facility, 'wifi') ||
            str_contains($facility, 'wi-fi')
                => 'fa-wifi',

            // Audio / sound system
            str_contains($facility, 'sound system') ||
            str_contains($facility, 'speaker') ||
            str_contains($facility, 'audio') ||
            str_contains($facility, 'microphone') ||
            str_contains($facility, 'mikrofon')
                => 'fa-volume-high',

            // TV
            str_contains($facility, 'tv') ||
            str_contains($facility, 'televisi')
                => 'fa-tv',

            // Kamera
            str_contains($facility, 'kamera') ||
            str_contains($facility, 'camera') ||
            str_contains($facility, 'video')
                => 'fa-camera',

            // Meja
            str_contains($facility, 'meja')
                => 'fa-table',

            // Kursi
            str_contains($facility, 'kursi')
                => 'fa-chair',

            // Alat praktikum
            str_contains($facility, 'alat praktikum') ||
            str_contains($facility, 'praktikum')
                => 'fa-flask',

            // Fasilitas kesehatan
            str_contains($facility, 'alat kesehatan') ||
            str_contains($facility, 'kesehatan') ||
            str_contains($facility, 'p3k')
                => 'fa-kit-medical',

            // Lemari / penyimpanan
            str_contains($facility, 'lemari') ||
            str_contains($facility, 'rak') ||
            str_contains($facility, 'penyimpanan')
                => 'fa-box-archive',

            // Sofa
            str_contains($facility, 'sofa')
                => 'fa-couch',

            // Blower / kipas
            str_contains($facility, 'blower') ||
            str_contains($facility, 'kipas')
                => 'fa-fan',

            // Listrik
            str_contains($facility, 'kabel') ||
            str_contains($facility, 'listrik') ||
            str_contains($facility, 'plug') ||
            str_contains($facility, 'stop kontak')
                => 'fa-plug',

            // Headset
            str_contains($facility, 'headset') ||
            str_contains($facility, 'earphone')
                => 'fa-headphones',

            default => 'fa-circle-check',
        };
    }

    protected function facilityStatusClasses(string $status): string
    {
        return match ($status) {
            'Disetujui' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300',
            'Ditolak' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300',
            'Dikembalikan' => 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
            default => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300',
        };
    }

    protected function statusSelectClasses(string $status): string
    {
        return match ($status) {
            'Disetujui' => 'border-emerald-200 bg-emerald-50 text-emerald-700 focus:border-emerald-500 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300',
            'Ditolak' => 'border-rose-200 bg-rose-50 text-rose-700 focus:border-rose-500 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300',
            'Dipinjam' => 'border-blue-200 bg-blue-50 text-blue-700 focus:border-blue-500 dark:border-blue-500/40 dark:bg-blue-500/10 dark:text-blue-300',
            'Dikembalikan' => 'border-slate-200 bg-slate-100 text-slate-700 focus:border-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
            'Selesai' => 'border-purple-200 bg-purple-50 text-purple-700 focus:border-purple-500 dark:border-purple-500/40 dark:bg-purple-500/10 dark:text-purple-300',
            default => 'border-amber-200 bg-amber-50 text-amber-700 focus:border-amber-500 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300',
        };
    }

    protected function setDetailFacilities(BorrowingDetail $detail, array $facilities): void
    {
        if (!Schema::hasColumn('borrowing_details', 'fasilitas')) {
            return;
        }

        $facilities = collect($facilities)->map(fn ($item) => trim((string) $item))->filter()->unique()->values()->all();
        $cast = method_exists($detail, 'getCasts') ? ($detail->getCasts()['fasilitas'] ?? null) : null;
        $detail->fasilitas = in_array($cast, ['array', 'json', 'collection', 'encrypted:array', 'encrypted:collection'], true)
            ? $facilities
            : json_encode($facilities, JSON_UNESCAPED_UNICODE);
    }

    protected function sendStatusNotification(Borrowing $borrowing, string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus || !in_array($newStatus, ['Disetujui', 'Ditolak', 'Selesai'], true)) {
            return;
        }

        $borrowing->loadMissing(['user', 'details.room', 'details.item']);

        $resourceNames = $borrowing->details
            ->map(fn ($detail) => $detail->room?->nama_ruangan ?? $detail->item?->nama_barang)
            ->filter()
            ->unique()
            ->values();

        $resourceName = $resourceNames->count() > 0
            ? $resourceNames->implode(', ')
            : 'fasilitas';

        $url = route('history');

        $notification = match ($newStatus) {
            'Disetujui' => [
                'title' => 'Pengajuan Disetujui',
                'message' => "Pengajuan Disetujui - Peminjaman {$resourceName} Anda siap digunakan.",
            ],
            'Ditolak' => [
                'title' => 'Pengajuan Ditolak',
                'message' => "Pengajuan Ditolak - {$resourceName} batal dipinjam. Catatan: " . ($borrowing->catatan_admin ?: '-'),
            ],
            'Selesai' => [
                'title' => 'Peminjaman Selesai',
                'message' => "Peminjaman Selesai - Terima kasih telah mengembalikan {$resourceName}.",
            ],
            default => null,
        };

        if (!$notification || !$borrowing->user_id) {
            return;
        }

        SystemNotification::create([
            'user_id' => $borrowing->user_id,
            'title' => $notification['title'],
            'message' => $notification['message'],
            'url' => $url,
            'is_read' => false,
        ]);
    }

    protected function activeStatuses(): array
    {
        return ['Menunggu', 'Disetujui', 'Dipinjam'];
    }

    public function reloadData(bool $notify = true): void
    {
        $this->dispatch('borrowing-updated');
        $this->refreshOpenDetail();
    }
    

    public function updatedApprovalStatus(string $value): void // fungsi ini dijalankan ketika approvalStatus diubah
    {
        $allowed = $this->approvalStatusOptions();
        if (!in_array($value, $allowed, true)) {
            return;
        }

        foreach ($this->approvalDetails as $index => $detail) {
            $original = $detail['status_original'] ?? $detail['status'];

            if ($this->approvalCurrentStatus === 'Menunggu' && $original === 'Menunggu') {
                if ($value === 'Disetujui' && $detail['status'] === 'Ditolak') {
                    return;
                }
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
                $q->where('status', 'Menunggu');
                if ($startDate) {
                    if ($endDate) {
                        $q->where('tanggal_mulai', '<', $endDate)
                            ->where('tanggal_selesai', '>', $startDate);
                    } else {
                        $q->where('tanggal_selesai', '>', $startDate);
                    }
                }
            });

        $earliestSubquery = Borrowing::query()
            ->select('tanggal_mulai')
            ->whereIn('status', $activeStatuses)
            ->when($startDate, function ($q) use ($startDate, $endDate) {
                if ($endDate) {
                    $q->where('tanggal_mulai', '<', $endDate)
                        ->where('tanggal_selesai', '>', $startDate);
                } else {
                    $q->where('tanggal_selesai', '>', $startDate);
                }
            })
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
                $q->whereIn('status', $activeStatuses);
                if ($startDate) {
                    if ($endDate) {
                        $q->where('tanggal_mulai', '<', $endDate)
                            ->where('tanggal_selesai', '>', $startDate);
                    } else {
                        $q->where('tanggal_selesai', '>', $startDate);
                    }
                }
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

        $pendingScheduleQuery = Borrowing::where('status', 'Menunggu');
        if ($startDate) {
            if ($endDate) {
                $pendingScheduleQuery->where('tanggal_mulai', '<', $endDate)
                    ->where('tanggal_selesai', '>', $startDate);
            } else {
                $pendingScheduleQuery->where('tanggal_selesai', '>', $startDate);
            }
        }

        return [
            'resources' => $resources,
            'pendingScheduleCount' => $pendingScheduleQuery->count(),
            'usersList' => User::orderBy('name')->get(),
            'roomsList' => Room::where('status_tersedia', true)->orderBy('nama_ruangan')->get(),
            'itemsList' => Item::where('bisa_dipinjam', true)->orderBy('nama_barang')->get(),
        ];
    }

    protected function scheduleQuery(bool $onlyPending = false)
    {
        $query = Borrowing::query()
            ->with(['user', 'details.room', 'details.item'])
            ->when($onlyPending, fn ($q) => $q->where('status', 'Menunggu'))
            ->when($this->scheduleSearch, function ($q) {
                $term = "%{$this->scheduleSearch}%";
                $q->where(function ($inner) use ($term) {
                    $inner->where('kode_transaksi', 'like', $term)
                        ->orWhere('tujuan', 'like', $term)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term));
                });
            });

        [$startDate, $endDate] = $this->dateRange();
        if ($startDate) {
            if ($endDate) {
                $query->where('tanggal_mulai', '<', $endDate)
                    ->where('tanggal_selesai', '>', $startDate);
            } else {
                $query->where('tanggal_selesai', '>', $startDate);
            }
        }

        return $query
            ->orderByRaw("CASE WHEN status = 'Menunggu' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
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
                if ($startDate) {
                    if ($endDate) {
                        $q->where('tanggal_mulai', '<', $endDate)
                            ->where('tanggal_selesai', '>', $startDate);
                    } else {
                        $q->where('tanggal_selesai', '>', $startDate);
                    }
                }
            })
            ->orderByRaw("CASE WHEN status = 'Menunggu' THEN 0 ELSE 1 END")
            ->orderByDesc('borrowing_id')
            ->orderBy('id')
            ->get()
            ->map(fn (BorrowingDetail $detail) => [
                'id' => $detail->id,
                'borrowing_id' => $detail->borrowing_id,
                'kode_transaksi' => $detail->borrowing?->kode_transaksi ?? '-',
                'peminjam' => $detail->borrowing?->user?->name ?? '-',
                'no_hp' => $detail->borrowing?->user?->no_hp ?? $detail->borrowing?->user?->no_wa ?? '-',
                'tujuan' => $detail->borrowing?->tujuan ?? '-',
                'tanggal_pengajuan' => optional($detail->borrowing?->created_at)->format('d M Y H:i'),
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
            'tanggal_pengajuan' => optional($borrowing->created_at)->format('d M Y H:i'),
            'tanggal_mulai' => optional($borrowing->tanggal_mulai)->format('d M Y H:i'),
            'tanggal_selesai' => optional($borrowing->tanggal_selesai)->format('d M Y H:i'),
            'catatan' => (string) ($borrowing->catatan ?? ''),
            'catatan_admin' => (string) ($borrowing->catatan_admin ?? ''),
            'file_lampiran' => $borrowing->file_lampiran ?? null,
            'details' => $borrowing->details->map(function ($detail) {
                $facilities = $this->detailFacilities($detail);

                return [
                    'id' => $detail->id,
                    'type' => $detail->room ? 'Ruangan' : 'Barang',
                    'room_id' => $detail->room_id,
                    'item_id' => $detail->item_id,
                    'name' => $detail->room?->nama_ruangan ?? $detail->item?->nama_barang ?? '-',
                    'code' => $detail->room?->kode_ruangan ?? $detail->item?->kode_barang ?? '-',
                    'jumlah' => (int) $detail->jumlah,
                    'status' => (string) $detail->status,
                    'catatan' => (string) ($detail->catatan ?? ''),
                    'catatan_pengembalian' => (string) ($detail->catatan_pengembalian ?? ''),
                    'file_bukti_pengembalian' => $detail->file_bukti_pengembalian ?? null,
                    'available_fasilitas' => $facilities['available'],
                    'fasilitas' => $facilities['selected'],
                ];
            })->toArray(),
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
        $borrowing = Borrowing::with(['details.room', 'details.item'])->findOrFail($borrowingId);

        $this->approvalBorrowingId = $borrowing->id;
        $this->approvalTransactionCode = $borrowing->kode_transaksi;
        $this->approvalCurrentStatus = (string) $borrowing->status;
        $this->approvalStatus = (string) $borrowing->status;
        $this->catatan = (string) ($borrowing->catatan ?? '');
        $this->catatan_admin = (string) ($borrowing->catatan_admin ?? '');
        $this->returnUploads = [];
        $this->approvalDetails = $borrowing->details->map(function ($detail) {
            $facilities = $this->detailFacilities($detail);

            return [
                'id' => $detail->id,
                'type' => $detail->room ? 'Ruangan' : 'Barang',
                'room_id' => $detail->room_id,
                'item_id' => $detail->item_id,
                'name' => $detail->room?->nama_ruangan ?? $detail->item?->nama_barang ?? '-',
                'code' => $detail->room?->kode_ruangan ?? $detail->item?->kode_barang ?? '-',
                'jumlah' => (int) $detail->jumlah,
                'status' => (string) $detail->status,
                'status_original' => (string) $detail->status,
                'catatan' => (string) ($detail->catatan ?? ''),
                'catatan_pengembalian' => (string) ($detail->catatan_pengembalian ?? ''),
                'file_bukti_pengembalian' => $detail->file_bukti_pengembalian ?? null,
                'available_fasilitas' => $facilities['available'],
                'fasilitas' => $facilities['selected'],
            ];
        })->toArray();

        $this->resetValidation();
        $this->isApprovalModalOpen = true;
    }

    public function toggleApprovalDetail(int $index): void
    {
        if (!isset($this->approvalDetails[$index])) {
            return;
        }

        $status = $this->approvalDetails[$index]['status'] ?? 'Menunggu';
        $this->approvalDetails[$index]['status'] = $status === 'Disetujui' ? 'Ditolak' : 'Disetujui';
    }

    public function isApprovalDetailChecked(array $detail): bool
    {
        return ($detail['status'] ?? '') === 'Disetujui';
    }

    public function closeApprovalModal(): void
    {
        $this->isApprovalModalOpen = false;
        $this->approvalBorrowingId = null;
        $this->approvalTransactionCode = '';
        $this->approvalStatus = 'Menunggu';
        $this->approvalCurrentStatus = 'Menunggu';
        $this->catatan = '';
        $this->catatan_admin = '';
        $this->returnUploads = [];
        $this->approvalDetails = [];
        $this->resetValidation();
    }

    public function approvalStatusOptions(): array
    {
        return match ($this->approvalCurrentStatus) {
            'Menunggu' => ['Menunggu', 'Disetujui', 'Ditolak'],
            'Disetujui' => ['Disetujui', 'Dikembalikan'],
            'Dipinjam' => ['Dipinjam', 'Dikembalikan'],
            'Dikembalikan' => ['Dikembalikan', 'Selesai'],
            'Selesai' => ['Selesai'],
            'Ditolak' => ['Ditolak'],
            default => [],
        };
    }

    public function detailStatusOptions(string $type = 'approval'): array
    {
        if ($type === 'edit') {
            return $this->statusOptions;
        }

        return $this->approvalStatusOptions();
    }

    public function saveApproval(): void
    {
        $options = $this->approvalStatusOptions();

        $rules = [
            'approvalStatus' => ['required', Rule::in($options)],
            'catatan_admin' => ['nullable', 'string', 'max:2000'],
            'approvalDetails.*.catatan' => ['nullable', 'string', 'max:2000'],
            'approvalDetails.*.catatan_pengembalian' => ['nullable', 'string', 'max:2000'],
        ];

        foreach ($this->approvalDetails as $index => $detail) {
            if (($detail['item_id'] ?? null) !== null) {
                $rules["approvalDetails.{$index}.jumlah"] = ['required', 'integer', 'min:1'];
            }
        }

        $isApproval = $this->approvalCurrentStatus === 'Menunggu';
        $isFollowUp = in_array($this->approvalCurrentStatus, ['Disetujui', 'Dipinjam'], true);

        if ($isFollowUp) {
            $rules['returnUploads.*'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic', 'max:1024'];
        }

        $this->validate($rules, [
            'returnUploads.*.max' => 'Bukti pengembalian maksimal 1 MB.',
            'returnUploads.*.mimes' => 'Bukti pengembalian harus berupa PDF atau gambar.',
        ]);

        $borrowing = Borrowing::with('details')->findOrFail($this->approvalBorrowingId);
        $oldStatus = (string) $borrowing->status;

        try {
            DB::transaction(function () use ($borrowing, $isApproval, $isFollowUp) {
                $detailsById = $borrowing->details->keyBy('id');

                foreach ($this->approvalDetails as $index => $payload) {
                    $detail = $detailsById->get((int) ($payload['id'] ?? 0));

                    if (!$detail) {
                        continue;
                    }

                    $current = (string) $detail->status;
                    $requested = (string) ($payload['status'] ?? $current);

                    if ($isApproval) {
                        if (!in_array($requested, ['Menunggu', 'Disetujui', 'Ditolak'], true)) {
                            throw new \RuntimeException('Status detail tidak valid.');
                        }
                    } elseif ($isFollowUp) {
                        $allowed = match ($current) {
                            'Disetujui' => ['Disetujui', 'Dikembalikan'],
                            'Dipinjam' => ['Dipinjam', 'Dikembalikan'],
                            default => [$current],
                        };

                        if (!in_array($requested, $allowed, true)) {
                            throw new \RuntimeException('Status tindak lanjut tidak valid.');
                        }
                    } else {
                        $requested = $current;
                    }

                    $detail->status = $requested;
                    $detail->catatan = trim((string) ($payload['catatan'] ?? '')) ?: null;

                    if ($detail->item_id !== null) {
                        $detail->jumlah = max(1, (int) ($payload['jumlah'] ?? $detail->jumlah));
                    } else {
                        $detail->jumlah = 1;
                    }

                    if (Schema::hasColumn('borrowing_details', 'fasilitas')) {
                        $this->setDetailFacilities($detail, $payload['fasilitas'] ?? []);
                    }

                    if ($isFollowUp) {
                        $returnNote = trim((string) ($payload['catatan_pengembalian'] ?? ''));

                        if (Schema::hasColumn('borrowing_details', 'catatan_pengembalian')) {
                            $detail->catatan_pengembalian = $returnNote ?: null;
                        }

                        $upload = $this->returnUploads[$index] ?? null;

                        if ($upload && Schema::hasColumn('borrowing_details', 'file_bukti_pengembalian')) {
                            $detail->file_bukti_pengembalian = $upload->store('bukti-pengembalian', 'public');
                        }
                    }

                    $detail->save();
                }

                $borrowing->catatan = trim($this->catatan) ?: null;
                $borrowing->catatan_admin = trim($this->catatan_admin) ?: null;

                if ($isApproval && Schema::hasColumn('borrowings', 'approved_by')) {
                    $borrowing->approved_by = auth()->id();
                }

                $borrowing->refresh()->load('details');
                $this->syncBorrowingStatus($borrowing);
                $borrowing->save();
            });

            $borrowing->refresh()->load(['user', 'details.room', 'details.item']);
            $newStatus = (string) $borrowing->status;
            $this->sendStatusNotification($borrowing, $oldStatus, $newStatus);
            $code = $borrowing->kode_transaksi;
            $this->closeApprovalModal();
            $this->dispatch('toast', type: 'success', message: "Data {$code} berhasil disimpan. Status transaksi: {$newStatus}.");
            $this->refreshOpenDetail();
        } catch (\Throwable $e) {
            report($e);
            $this->addError('approvalStatus', 'Terjadi kesalahan saat menyimpan data. Periksa isian dan coba lagi.');
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
            'formFile' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic', 'max:1024'],
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

    public function openEditModal(int $borrowingId): void
    {
        $borrowing = Borrowing::with(['details.room', 'details.item'])->findOrFail($borrowingId);

        $this->editBorrowingId = $borrowing->id;
        $this->editForm = [
            'kode_transaksi' => (string) ($borrowing->kode_transaksi ?? ''),
            'user_id' => (string) ($borrowing->user_id ?? ''),
            'tanggal_mulai' => optional($borrowing->tanggal_mulai)->format('Y-m-d\TH:i'),
            'tanggal_selesai' => optional($borrowing->tanggal_selesai)->format('Y-m-d\TH:i'),
            'tujuan' => (string) ($borrowing->tujuan ?? ''),
            'status' => (string) ($borrowing->status ?? 'Menunggu'),
            'catatan' => (string) ($borrowing->catatan ?? ''),
            'catatan_admin' => (string) ($borrowing->catatan_admin ?? ''),
        ];
        $this->editFile = null;
        $this->returnUploads = [];
        $this->editDetails = $borrowing->details->map(function ($detail) {
            $facilities = $this->detailFacilities($detail);

            return [
                'id' => $detail->id,
                'type' => $detail->room ? 'Ruangan' : 'Barang',
                'room_id' => $detail->room_id,
                'item_id' => $detail->item_id,
                'name' => $detail->room?->nama_ruangan ?? $detail->item?->nama_barang ?? '-',
                'code' => $detail->room?->kode_ruangan ?? $detail->item?->kode_barang ?? '-',
                'jumlah' => (int) $detail->jumlah,
                'status' => (string) $detail->status,
                'catatan' => (string) ($detail->catatan ?? ''),
                'catatan_pengembalian' => (string) ($detail->catatan_pengembalian ?? ''),
                'file_bukti_pengembalian' => $detail->file_bukti_pengembalian ?? null,
                'available_fasilitas' => $facilities['available'],
                'fasilitas' => $facilities['selected'],
            ];
        })->toArray();

        $this->resetValidation();
        $this->isEditModalOpen = true;
    }

    public function closeEditModal(): void
    {
        $this->isEditModalOpen = false;
        $this->editBorrowingId = null;
        $this->editForm = [
            'user_id' => '',
            'tanggal_mulai' => '',
            'tanggal_selesai' => '',
            'tujuan' => '',
            'status' => 'Menunggu',
            'catatan' => '',
            'catatan_admin' => '',
        ];
        $this->editDetails = [];
        $this->editFile = null;
        $this->returnUploads = [];
        $this->resetValidation();
    }

    public function removeEditDetail(int $index): void
    {
        if (isset($this->editDetails[$index])) {
            unset($this->editDetails[$index]);
            $this->editDetails = array_values($this->editDetails);
        }
    }

    public function saveEdit(): void
    {
        foreach ($this->editDetails as $index => $detail) {
            if (($detail['item_id'] ?? null) !== null) {
                $this->editDetails[$index]['jumlah'] = max(1, (int) ($detail['jumlah'] ?? 1));
            } else {
                $this->editDetails[$index]['jumlah'] = 1;
            }
        }

        $this->validate([
            'editForm.user_id' => ['required', 'exists:users,id'],
            'editForm.tanggal_mulai' => ['required', 'date'],
            'editForm.tanggal_selesai' => ['required', 'date', 'after_or_equal:editForm.tanggal_mulai'],
            'editForm.tujuan' => ['required', 'string', 'max:1000'],
            'editForm.status' => ['required', Rule::in($this->statusOptions)],
            'editForm.catatan' => ['nullable', 'string', 'max:2000'],
            'editForm.catatan_admin' => ['nullable', 'string', 'max:2000'],
            'editFile' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic', 'max:1024'],
            'editDetails.*.catatan' => ['nullable', 'string', 'max:2000'],
            'editDetails.*.catatan_pengembalian' => ['nullable', 'string', 'max:2000'],
            'editDetails.*.jumlah' => ['required', 'integer', 'min:1'],
            'editDetails.*.status' => ['required', Rule::in($this->statusOptions)],
            'returnUploads.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic', 'max:1024'],
        ], [
            'returnUploads.*.max' => 'Bukti pengembalian maksimal 1 MB.',
            'returnUploads.*.mimes' => 'Bukti pengembalian harus berupa PDF atau gambar.',
        ]);

        $borrowing = Borrowing::with('details')->findOrFail($this->editBorrowingId);
        $oldStatus = (string) $borrowing->status;

        try {
            DB::transaction(function () use ($borrowing) {
                $borrowing->user_id = $this->editForm['user_id'];
                $borrowing->tanggal_mulai = $this->editForm['tanggal_mulai'];
                $borrowing->tanggal_selesai = $this->editForm['tanggal_selesai'];
                $borrowing->tujuan = $this->editForm['tujuan'];
                $borrowing->status = $this->editForm['status'];
                $borrowing->catatan = trim($this->editForm['catatan']) ?: null;
                $borrowing->catatan_admin = trim($this->editForm['catatan_admin']) ?: null;

                if ($this->editFile && Schema::hasColumn('borrowings', 'file_lampiran')) {
                    $borrowing->file_lampiran = $this->editFile->store('bukti-peminjaman', 'public');
                }

                $borrowing->save();

                $detailsById = $borrowing->details->keyBy('id');

                foreach ($this->editDetails as $index => $payload) {
                    $detail = $detailsById->get((int) ($payload['id'] ?? 0));

                    if (!$detail) {
                        continue;
                    }

                    $detail->catatan = trim((string) ($payload['catatan'] ?? '')) ?: null;
                    $detail->catatan_pengembalian = trim((string) ($payload['catatan_pengembalian'] ?? '')) ?: null;
                    $detail->jumlah = $detail->item_id !== null ? max(1, (int) ($payload['jumlah'] ?? 1)) : 1;
                    $detail->status = $payload['status'] ?? $detail->status;
                    $this->setDetailFacilities($detail, $payload['fasilitas'] ?? []);

                    $upload = $this->returnUploads[$index] ?? null;

                    if ($upload && Schema::hasColumn('borrowing_details', 'file_bukti_pengembalian')) {
                        $detail->file_bukti_pengembalian = $upload->store('bukti-pengembalian', 'public');
                    }

                    $detail->save();
                }
            });

            $borrowing->refresh()->load(['user', 'details.room', 'details.item']);
            $this->sendStatusNotification($borrowing, $oldStatus, (string) $borrowing->status);
            $code = $borrowing->kode_transaksi;
            $this->closeEditModal();
            $this->dispatch('toast', type: 'success', message: "Data {$code} berhasil diperbarui.");

            if ($this->isBorrowingDetailModalOpen) {
                $this->openBorrowingDetailModal($borrowing->id);
            }
        } catch (\Throwable $e) {
            report($e);
            $this->addError('editForm.status', 'Gagal menyimpan perubahan data. Periksa kembali isian.');
        }
    }

    public function downloadApprovalPdf(int $borrowingId)
    {
        $borrowing = Borrowing::with(['user', 'details.room', 'details.item'])->findOrFail($borrowingId);

        if (!class_exists(Mpdf::class)) {
            $this->dispatch('toast', type: 'error', message: 'Library mPDF belum terpasang.');
            return null;
        }

        $html = view('livewire.pdf.booking-approval', [
            'borrowing' => $borrowing,
            'catatan' => $this->borrowingNote($borrowing),
            'catatanPengembalian' => $this->borrowingReturnNote($borrowing),
            'buktiPengembalian' => $this->borrowingReturnProof($borrowing),
        ])->render();

        $tempDir = storage_path('app/mpdf');

        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 10,
            'margin_right' => 18,
            'margin_bottom' => 10,
            'margin_left' => 18,
            'tempDir' => $tempDir,
             // Jarak header/footer
            'margin_header' => 5,
            'margin_footer' => 5,
        ]);

        $mpdf->SetTitle('Surat Persetujuan ' . $borrowing->kode_transaksi);
        $mpdf->SetHTMLFooter('
            <table width="100%" style="border-collapse: collapse;">
                <tr>
                    <td
                        style="
                            text-align:center;
                            font-family:dejavusans;
                            font-size:7.5pt;
                            color:#000000;
                            padding:0;
                        "
                    >
                        Dokumen ini dibuat secara elektronik melalui Sistem Sarana dan Prasarana
                        SMA Negeri 1 Pekalongan.
                    </td>
                </tr>
            </table>
        ');

        $mpdf->WriteHTML($html);

        $filename = 'Surat-Persetujuan-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $borrowing->kode_transaksi) . '.pdf';
        $pdfPath = $tempDir . DIRECTORY_SEPARATOR . $filename;

        $mpdf->Output($pdfPath, Destination::FILE);

        return response()->download($pdfPath, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ])->deleteFileAfterSend(true);
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
.scrollbar-hidden {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.scrollbar-hidden::-webkit-scrollbar {
    display: none;
    width: 0;
    height: 0;
}

.responsive-table {
    width: 100%;
    overflow-x: auto;
    overflow-y: visible;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.responsive-table::-webkit-scrollbar {
    display: none;
    width: 0;
    height: 0;
}

.responsive-table>table {
    min-width: 760px;
}

@media (max-width: 640px) {
    .responsive-table>table {
        min-width: 700px;
    }
}

.select2-container {
    width: 100% !important;
}

.select2-container--default .select2-selection--single {
    min-height: 44px;
    display: flex;
    align-items: center;
    border: 0;
    border-radius: .75rem;
    background: rgb(248 250 252);
}

.dark .select2-container--default .select2-selection--single {
    background: rgb(31 41 55);
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    padding-left: 1rem;
    font-size: .875rem;
}

.select2-container--default .select2-selection--single.status-menunggu {border-color:#fcd34d;background:#fffbeb;color:#b45309;}
.select2-container--default .select2-selection--single.status-disetujui {border-color:#6ee7b7;background:#ecfdf5;color:#047857;}
.select2-container--default .select2-selection--single.status-ditolak {border-color:#fda4af;background:#fff1f2;color:#be123c;}
.select2-container--default .select2-selection--single.status-dipinjam {border-color:#93c5fd;background:#eff6ff;color:#1d4ed8;}
.select2-container--default .select2-selection--single.status-dikembalikan {border-color:#cbd5e1;background:#f8fafc;color:#475569;}
.select2-container--default .select2-selection--single.status-selesai {border-color:#c4b5fd;background:#faf5ff;color:#7e22ce;}
.dark .select2-container--default .select2-selection--single.status-menunggu {background:rgba(245,158,11,.1);color:#fcd34d;}
.dark .select2-container--default .select2-selection--single.status-disetujui {background:rgba(16,185,129,.1);color:#6ee7b7;}
.dark .select2-container--default .select2-selection--single.status-ditolak {background:rgba(244,63,94,.1);color:#fda4af;}
.dark .select2-container--default .select2-selection--single.status-dipinjam {background:rgba(59,130,246,.1);color:#93c5fd;}
.dark .select2-container--default .select2-selection--single.status-dikembalikan {background:#1f2937;color:#cbd5e1;}
.dark .select2-container--default .select2-selection--single.status-selesai {background:rgba(139,92,246,.1);color:#c4b5fd;}
</style>

<div wire:poll.30s="reloadData(false)">
    <div class="flex flex-col gap-4 mb-6 sm:mb-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl dark:text-white">Dashboard
                    Peminjaman</h1>
                <p class="mt-1 text-xs font-medium leading-relaxed text-gray-500 sm:text-sm dark:text-gray-400">
                    Monitoring status ketersediaan & pengajuan peminjaman realtime.</p>
            </div>
            <div class="flex flex-wrap gap-2 p-1 bg-gray-100 rounded-2xl dark:bg-gray-800">
                <button type="button" wire:click="reloadData" wire:loading.attr="disabled" wire:target="reloadData"
                    class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-bold text-slate-700 transition-all bg-white rounded-xl shadow-sm hover:bg-slate-50 active:scale-95 disabled:cursor-wait disabled:opacity-70 dark:bg-gray-900 dark:text-slate-200 dark:hover:bg-gray-800">
                    <span wire:loading.remove wire:target="reloadData"><i
                            class="fa-solid fa-rotate mr-1.5"></i>Reload</span>
                    <span wire:loading wire:target="reloadData"><i
                            class="fa-solid fa-rotate mr-1.5 animate-spin"></i>Memuat...</span>
                </button>
                <button type="button" wire:click="openAddModal"
                    class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-bold text-white transition-all bg-emerald-600 rounded-xl hover:bg-emerald-700 active:scale-95 shadow-md shadow-emerald-500/20">
                    <i class="fa-solid fa-plus"></i>Tambah
                </button>
                <button type="button" wire:click="openScheduleModal"
                    class="relative inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-bold text-white transition-all bg-slate-800 rounded-xl hover:bg-slate-900 active:scale-95 dark:bg-slate-700 dark:hover:bg-slate-600">
                    <i class="fa-solid fa-calendar-days"></i>Info Jadwal
                    @if($pendingScheduleCount > 0)
                    <span
                        class="absolute flex items-center justify-center min-w-5 h-5 px-1.5 text-[10px] font-bold text-white bg-red-500 rounded-full -top-2 -right-2 ring-2 ring-white dark:ring-gray-900">{{ $pendingScheduleCount > 99 ? '99+' : $pendingScheduleCount }}</span>
                    @endif
                </button>
                <button type="button" wire:click="$set('tab', 'room')"
                    class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-bold rounded-xl transition-all active:scale-95 {{ $tab === 'room' ? 'bg-white text-indigo-600 shadow-md dark:bg-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">
                    <i class="fa-solid fa-door-open"></i>Ruangan
                </button>
                <button type="button" wire:click="$set('tab', 'item')"
                    class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-bold rounded-xl transition-all active:scale-95 {{ $tab === 'item' ? 'bg-white text-indigo-600 shadow-md dark:bg-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">
                    <i class="fa-solid fa-box-open"></i>Barang
                </button>
            </div>
        </div>
    </div>
    <div
        class="p-4 mb-6 bg-white border border-gray-200 shadow-sm sm:p-6 sm:mb-8 rounded-2xl sm:rounded-3xl dark:bg-gray-900 dark:border-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-12 md:items-end">
            <div class="relative md:col-span-5">
                <div class="absolute inset-y-0 left-0 z-10 flex items-center pl-4 pointer-events-none">
                    <i class="text-[15px] leading-none text-gray-400 fa-solid fa-magnifying-glass mt-[-1px]"></i>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search"
                    class="w-full py-3 pl-11 pr-4 text-sm font-medium transition-all border rounded-xl border-slate-200 bg-slate-50 text-slate-700 placeholder:text-slate-400 shadow-sm outline-none hover:border-slate-300 hover:bg-white focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500 dark:hover:border-slate-600"
                    placeholder="Cari {{ $tab === 'room' ? 'nama / kode ruangan' : 'nama / kode barang' }}...">
            </div>
            <div class="grid grid-cols-1 gap-3 md:col-span-7 sm:grid-cols-[1fr_auto_1fr] sm:items-end">
                <div>
                    <label class="block mb-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400">Dari
                        Tanggal</label>

                    <input type="date" wire:model.live="dateFrom"
                        class="w-full px-3 py-3 text-sm font-medium transition-all border outline-none border-slate-200 bg-gray-50 rounded-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                </div>

                <div class="hidden sm:flex h-[46px] items-center text-gray-400">
                    -
                </div>

                <div>
                    <label class="block mb-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400">Sampai
                        Tanggal</label>

                    <input type="date" wire:model.live="dateTo"
                        class="w-full px-3 py-3 text-sm font-medium transition-all border outline-none border-slate-200 bg-gray-50 rounded-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                </div>
            </div>

        </div>
    </div>
    <div class="relative">
        <div wire:loading.flex wire:target="reloadData,search,dateFrom,dateTo"
            class="absolute inset-0 z-20 items-start justify-center pt-10 bg-white/70 backdrop-blur-[2px] rounded-3xl dark:bg-gray-900/70">
            <div
                class="flex items-center gap-3 px-4 py-3 text-xs font-bold text-indigo-600 bg-white border border-indigo-100 shadow-lg rounded-2xl dark:bg-gray-900 dark:border-indigo-900/50 dark:text-indigo-400">
                <span class="flex items-center justify-center w-7 h-7 rounded-xl bg-indigo-50 dark:bg-indigo-900/30"><i
                        class="fa-solid fa-spinner animate-spin"></i></span>
                <span>Memuat data...</span>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($resources as $res)
            @php
            $name = $tab === 'room' ? $res->nama_ruangan : $res->nama_barang;
            $code = $tab === 'room' ? $res->kode_ruangan : $res->kode_barang;
            $capacity = $tab === 'room' ? $res->kapasitas . ' Orang' : $res->jumlah_total . ' Unit';
            $hasPending = $res->pending_count > 0;
            @endphp
            <div
                class="relative flex flex-col justify-between overflow-hidden transition-all bg-white border border-gray-200 shadow-sm rounded-3xl dark:bg-gray-900 dark:border-gray-800 hover:shadow-lg hover:border-indigo-200 dark:hover:border-indigo-900">
                <div class="p-6 border-b border-gray-100 dark:border-gray-800/60">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center min-w-0 gap-4">
                            <div
                                class="flex items-center justify-center flex-shrink-0 text-indigo-600 rounded-2xl w-14 h-14 bg-indigo-50 dark:bg-indigo-900/30 dark:text-indigo-400">
                                <i
                                    class="text-2xl {{ $res->icon ?: ($tab === 'room' ? 'fa-solid fa-door-closed' : 'fa-solid fa-box') }}"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-bold text-gray-900 truncate text-md dark:text-white">{{ $name }}</h3>
                                <div class="flex items-center gap-2 mt-1"><span
                                        class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $tab === 'room' ? 'Ruangan' : 'Barang' }}</span><span
                                        class="inline-flex items-center px-2.5 py-0.5 text-[10px] font-bold text-indigo-600 bg-indigo-50 rounded-md dark:bg-indigo-950 dark:text-indigo-300">#{{ $code }}</span>
                                </div>
                            </div>
                        </div>
                        <button wire:click="openDetailModal('{{ $tab }}', {{ $res->id }}, @js($name))"
                            title="{{ $hasPending ? 'Ada pengajuan menunggu approval' : 'Info peminjaman' }}"
                            class="relative flex flex-shrink-0 items-center justify-center rounded-xl w-10 h-10 {{ $hasPending ? 'bg-red-100 text-red-600 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300' : 'bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400' }}">
                            @if($hasPending)<span
                                class="absolute -top-1 -right-1 flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-red-500 text-white text-[9px] font-bold ring-2 ring-white dark:ring-gray-900">{{ $res->pending_count > 99 ? '99+' : $res->pending_count }}</span>@endif
                            <i
                                class="fa-solid {{ $hasPending ? 'fa-bell animate-pulse' : 'fa-circle-info' }} text-xs"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6 space-y-3">
                    <div class="flex items-center justify-between text-xs"><span
                            class="font-semibold text-gray-400 uppercase">Kapasitas / Stok</span><span
                            class="font-bold text-gray-900 dark:text-white">{{ $capacity }}</span></div>
                    <div class="flex items-center justify-between text-xs"><span
                            class="font-semibold text-gray-400 uppercase">Total Agenda</span><span
                            class="font-bold text-indigo-600 dark:text-indigo-400">{{ $res->total_active_count }}
                            Pengajuan</span></div>
                    <div class="flex items-center justify-between text-xs"><span
                            class="font-semibold text-gray-400 uppercase">Agenda Terdekat</span><span
                            class="font-bold text-gray-700 dark:text-gray-200">{{ $res->earliest_booking_date ? Carbon::parse($res->earliest_booking_date)->format('d M Y H:i') : '-' }}</span>
                    </div>
                </div>
                <div
                    class="p-4 border-t border-gray-100 bg-gray-50/50 rounded-b-3xl dark:border-gray-800/60 dark:bg-gray-800/20">
                    <button wire:click="openDetailModal('{{ $tab }}', {{ $res->id }}, @js($name))"
                        class="flex items-center justify-center w-full gap-2 px-4 py-2.5 text-xs font-bold text-white transition-all bg-indigo-600 shadow-md rounded-2xl hover:bg-indigo-700 shadow-indigo-500/20"><i
                            class="fa-solid fa-list-check"></i> Data Peminjaman</button>
                </div>
            </div>
            @empty
            <div
                class="col-span-full py-16 text-center bg-white border border-gray-200 shadow-sm rounded-3xl dark:bg-gray-900 dark:border-gray-800">
                <i class="mb-3 text-4xl text-gray-300 fa-solid fa-folder-open dark:text-gray-700"></i>
                <p class="text-sm font-semibold text-gray-400">Tidak ada data
                    {{ $tab === 'room' ? 'ruangan' : 'barang' }} ditemukan.</p>
            </div>
            @endforelse
        </div>
    </div>

    <div class="mt-8">{{ $resources->links() }}</div>
    <section x-data="{ open: @entangle('isScheduleModalOpen') }">
        <template x-teleport="body">
            <div x-show="open" class="fixed inset-0 z-999 flex items-center justify-center p-4" x-cloak>
                <div class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm"></div>
                <div x-show="open" x-transition
                    class="relative z-[101] w-full max-w-6xl max-h-[90vh] overflow-hidden bg-white shadow-2xl dark:bg-gray-900 rounded-3xl hide-scrollbar">
                    <div
                        class="flex items-center justify-between gap-4 px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 dark:text-white"><i
                                    class="mr-2 text-indigo-500 fa-solid fa-calendar-days"></i> Info Jadwal Peminjaman
                            </h4>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                @if($dateFrom || $dateTo)
                                    {{ $dateFrom ? Carbon::parse($dateFrom)->format('d M Y') : 'Awal' }} - {{ $dateTo ? Carbon::parse($dateTo)->format('d M Y') : 'Akhir' }}
                                @else
                                    Semua tanggal
                                @endif</p>
                        </div>
                        <button type="button" wire:click="closeScheduleModal"
                            class="flex items-center justify-center w-9 h-9 text-gray-500 rounded-xl bg-gray-100 hover:text-red-500 dark:bg-gray-800">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                        <div class="relative">
                            <i
                                class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 fa-solid fa-magnifying-glass"></i>
                            <input type="text" wire:model.live.debounce.300ms="scheduleSearch"
                                placeholder="Cari kode transaksi, nama peminjam, atau tujuan..."
                                class="w-full py-3 pl-11 pr-4 text-sm border border-slate-200 rounded-xl bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="overflow-x-auto overflow-y-auto max-h-[58vh] hide-scrollbar">
                        @php($scheduleRows = $this->scheduleQuery()->paginate(10, ['*'], 'schedulePage'))
                        <table class="w-full min-w-[760px] text-xs text-left">
                            <thead class="sticky top-0 z-10 bg-slate-50 dark:bg-slate-800">
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 font-bold uppercase text-slate-400">Kode Trx</th>
                                    <th class="px-4 py-3 font-bold uppercase text-slate-400">Nama Peminjam</th>
                                    <th class="px-4 py-3 font-bold uppercase text-slate-400">Tanggal Pengajuan</th>
                                    <th class="px-4 py-3 font-bold uppercase text-slate-400">Tujuan</th>
                                    <th class="px-4 py-3 font-bold uppercase text-slate-400">Waktu</th>
                                    <th class="px-4 py-3 font-bold text-center uppercase text-slate-400">Status</th>
                                    <th class="px-4 py-3 font-bold text-center uppercase text-slate-400">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($scheduleRows as $booking)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="px-4 py-4 font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ $booking->kode_transaksi }}</td>
                                    <td class="px-4 py-4 font-semibold text-gray-900 dark:text-white">
                                        {{ $booking->user?->name ?? '-' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-gray-600 dark:text-gray-300">
                                        {{ optional($booking->created_at)->format('d M Y H:i') }}</td>
                                    <td class="max-w-xs px-4 py-4 text-gray-600 dark:text-gray-300">
                                        {{ $booking->tujuan }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="font-semibold text-gray-900 dark:text-white">
                                            {{ optional($booking->tanggal_mulai)->format('d M Y H:i') }}</div>
                                        <div class="text-[10px] text-gray-400">s/d
                                            {{ optional($booking->tanggal_selesai)->format('d M Y H:i') }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-center"><span
                                            class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold {{ $this->statusBadgeClass($booking->status) }}">{{ $booking->status }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <div class="flex flex-wrap items-center justify-center gap-2">
                                            @if(in_array($booking->status, ['Menunggu', 'Disetujui'], true))
                                            <button wire:click="openApprovalModal({{ $booking->id }})"
                                                class="px-3 py-2 text-[10px] font-bold text-white rounded-lg {{ $booking->status === 'Menunggu' ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-slate-600 hover:bg-slate-700' }}">
                                                <i
                                                    class="mr-1 fa-solid {{ $booking->status === 'Menunggu' ? 'fa-gavel' : 'fa-pen-to-square' }}"></i>{{ $booking->status === 'Menunggu' ? 'Approve' : 'Tindak Lanjut' }}
                                            </button>
                                            @endif
                                            <button wire:click="openBorrowingDetailModal({{ $booking->id }})"
                                                class="px-3 py-2 text-[10px] font-bold text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200">
                                                <i class="mr-1 fa-solid fa-eye"></i>Detail
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-12 text-center text-gray-400">Tidak ada data
                                        peminjaman pada rentang tanggal ini.</td>
                                </tr>
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
    <section x-data="{ open: @entangle('isDetailModalOpen') }">
        <template x-teleport="body">
            <div x-show="open" x-cloak class="fixed inset-0 z-[9991] flex items-center justify-center p-3 sm:p-4">
                <div class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm"></div>
                <div x-show="open" x-transition
                    class="relative z-[111] flex w-full max-w-5xl max-h-[94vh] flex-col overflow-hidden rounded-2xl sm:rounded-3xl bg-white shadow-2xl dark:bg-gray-900">
                    <div
                        class="sticky top-0 z-30 flex shrink-0 items-center justify-between gap-3 px-4 py-4 sm:px-6 sm:py-5 bg-white/95 border-b border-gray-100 dark:bg-gray-900/95 dark:border-gray-800 backdrop-blur">
                        <div class="min-w-0">
                            <h4 class="text-lg font-bold text-gray-900 sm:text-xl dark:text-white">Data Peminjaman</h4>
                            <p
                                class="mt-1 truncate text-[10px] font-bold text-indigo-600 sm:text-xs dark:text-indigo-400">
                                {{ $selectedResourceName }}</p>
                        </div>
                        <button type="button" wire:click="closeDetailModal" aria-label="Tutup modal"
                            class="flex items-center justify-center w-9 h-9 shrink-0 text-gray-500 rounded-xl bg-gray-100 hover:bg-red-50 hover:text-red-500 dark:bg-gray-800 dark:hover:bg-red-900/20">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="hide-scrollbar scrollbar-hidden min-h-0 flex-1 overflow-y-auto">
                        <div class="p-4 sm:p-6">
                            <div
                                class="hidden overflow-hidden border rounded-2xl border-slate-200 dark:border-gray-800 md:block">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs text-left">
                                        <thead class="sticky top-0 z-10 bg-slate-50 dark:bg-slate-800">
                                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                                <th
                                                    class="px-4 py-3 font-bold uppercase whitespace-nowrap text-slate-400">
                                                    Kode Trx</th>
                                                <th class="px-4 py-3 font-bold uppercase text-slate-400">Peminjam</th>
                                                <th class="px-4 py-3 font-bold uppercase text-slate-400">Tujuan</th>
                                                <th
                                                    class="px-4 py-3 font-bold uppercase whitespace-nowrap text-slate-400">
                                                    Waktu</th>
                                                <th
                                                    class="px-4 py-3 font-bold text-center uppercase whitespace-nowrap text-slate-400">
                                                    Status</th>
                                                <th
                                                    class="px-4 py-3 font-bold text-center uppercase whitespace-nowrap text-slate-400">
                                                    Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                            @forelse($activeBorrowings as $item)
                                            <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                                <td
                                                    class="px-4 py-3 font-bold text-indigo-600 whitespace-nowrap dark:text-indigo-400">
                                                    {{ $item['kode_transaksi'] }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="font-bold text-gray-900 dark:text-white">
                                                        {{ $item['peminjam'] }}</div>
                                                    <div class="text-[10px] text-gray-400">{{ $item['no_hp'] }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                                    <div class="max-w-[220px] truncate" title="{{ $item['tujuan'] }}">
                                                        {{ $item['tujuan'] }}</div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <div class="font-semibold text-gray-900 dark:text-white">
                                                        {{ $item['tanggal_mulai'] }}</div>
                                                    <div class="text-[10px] text-gray-400">s/d
                                                        {{ $item['tanggal_selesai'] }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                                    <span
                                                        class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold {{ $this->statusBadgeClass($item['status']) }}">
                                                        {{ $item['status'] }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                                    @if($item['status'] === 'Menunggu')
                                                    <button type="button"
                                                        wire:click="openApprovalModal({{ $item['borrowing_id'] }})"
                                                        class="inline-flex items-center px-3 py-2 text-[10px] font-bold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                                        <i class="mr-1 fa-solid fa-gavel"></i> Approve
                                                    </button>
                                                    @elseif($item['status'] === 'Disetujui')
                                                    <button type="button"
                                                        wire:click="openApprovalModal({{ $item['borrowing_id'] }})"
                                                        class="inline-flex items-center px-3 py-2 text-[10px] font-bold text-white bg-slate-600 rounded-lg hover:bg-slate-700">
                                                        <i class="mr-1 fa-solid fa-pen-to-square"></i> Tindak Lanjut
                                                    </button>
                                                    @else
                                                    <button type="button"
                                                        wire:click="openBorrowingDetailModal({{ $item['borrowing_id'] }})"
                                                        class="inline-flex items-center px-3 py-2 text-[10px] font-bold text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200">
                                                        <i class="mr-1 fa-solid fa-eye"></i> Detail
                                                    </button>
                                                    @endif
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                                                    Tidak ada agenda peminjaman aktif pada rentang tanggal ini.
                                                </td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="space-y-3 md:hidden">
                                @forelse($activeBorrowings as $item)
                                <div
                                    class="overflow-hidden border rounded-2xl border-slate-200 bg-white shadow-sm dark:bg-gray-900 dark:border-gray-800">
                                    <div class="p-4 border-b border-slate-100 dark:border-gray-800">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400">
                                                    {{ $item['kode_transaksi'] }}
                                                </div>
                                                <div
                                                    class="mt-1 text-sm font-bold text-gray-900 truncate dark:text-white">
                                                    {{ $item['peminjam'] }}
                                                </div>
                                                <div class="mt-0.5 text-[10px] text-gray-400">
                                                    {{ $item['no_hp'] }}
                                                </div>
                                            </div>
                                            <span
                                                class="inline-flex shrink-0 px-2.5 py-1 rounded-full text-[9px] font-bold {{ $this->statusBadgeClass($item['status']) }}">
                                                {{ $item['status'] }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2">
                                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-gray-800/70">
                                            <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">
                                                Tujuan</div>
                                            <div
                                                class="mt-1 text-xs leading-relaxed text-gray-700 break-words dark:text-gray-300">
                                                {{ $item['tujuan'] ?: '-' }}
                                            </div>
                                        </div>

                                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-gray-800/70">
                                            <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">
                                                Waktu</div>
                                            <div class="mt-1 text-xs font-semibold text-gray-900 dark:text-white">
                                                {{ $item['tanggal_mulai'] }}
                                            </div>
                                            <div class="mt-0.5 text-[10px] text-gray-400">
                                                s/d {{ $item['tanggal_selesai'] }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-2 p-4 pt-0 sm:flex-row">
                                        @if($item['status'] === 'Menunggu')
                                        <button type="button"
                                            wire:click="openApprovalModal({{ $item['borrowing_id'] }})"
                                            class="inline-flex items-center justify-center w-full px-3 py-2.5 text-[10px] font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700">
                                            <i class="mr-1 fa-solid fa-gavel"></i> Approve
                                        </button>
                                        @elseif($item['status'] === 'Disetujui')
                                        <button type="button"
                                            wire:click="openApprovalModal({{ $item['borrowing_id'] }})"
                                            class="inline-flex items-center justify-center w-full px-3 py-2.5 text-[10px] font-bold text-white bg-slate-600 rounded-xl hover:bg-slate-700">
                                            <i class="mr-1 fa-solid fa-pen-to-square"></i> Tindak Lanjut
                                        </button>
                                        @else
                                        <button type="button"
                                            wire:click="openBorrowingDetailModal({{ $item['borrowing_id'] }})"
                                            class="inline-flex items-center justify-center w-full px-3 py-2.5 text-[10px] font-bold text-slate-700 bg-slate-100 rounded-xl hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200">
                                            <i class="mr-1 fa-solid fa-eye"></i> Detail
                                        </button>
                                        @endif
                                    </div>
                                </div>
                                @empty
                                <div class="p-8 text-center border rounded-2xl border-slate-200 dark:border-gray-800">
                                    <div
                                        class="flex items-center justify-center w-10 h-10 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-gray-800">
                                        <i class="text-sm fa-solid fa-calendar-xmark text-slate-400"></i>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        Tidak ada agenda peminjaman aktif pada rentang tanggal ini.
                                    </div>
                                </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div
                        class="shrink-0 p-4 sm:px-6 sm:py-4 border-t border-gray-100 bg-white/95 backdrop-blur dark:bg-gray-900/95 dark:border-gray-800">
                        <div class="flex justify-end">
                            <button type="button" wire:click="closeDetailModal"
                                class="w-full px-5 py-3 text-sm font-bold text-gray-700 bg-gray-100 rounded-xl sm:w-auto sm:py-2.5 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </section>

    <section x-data="{ open: @entangle('isBorrowingDetailModalOpen') }">
        <template x-teleport="body">
            <div x-show="open" x-cloak class="fixed inset-0 z-[9992] flex items-center justify-center p-3 sm:p-4">
                <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm"></div>
                <div x-show="open" x-transition class="relative z-[151] flex w-full max-w-6xl max-h-[95vh] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900 sm:rounded-3xl">
                    <div class="sticky top-0 z-30 flex shrink-0 items-center justify-between gap-3 border-b border-gray-100 bg-white/95 px-4 py-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 sm:px-6 sm:py-5">
                        <div class="min-w-0">
                            <h4 class="text-base font-bold text-gray-900 dark:text-white sm:text-lg">Detail Peminjaman</h4>
                            <p class="mt-1 truncate text-[10px] font-bold text-indigo-600 dark:text-indigo-400 sm:text-xs">{{ $selectedBorrowing['kode_transaksi'] ?? '-' }}</p>
                        </div>
                        <button type="button" wire:click="closeBorrowingDetailModal" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-500 hover:bg-red-50 hover:text-red-500 dark:bg-gray-800 dark:hover:bg-red-900/20"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto scrollbar-hidden">
                        <div class="space-y-6 p-4 sm:p-6">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800">
                                    <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Nama Peminjam</div>
                                    <div class="mt-1 truncate text-sm font-bold text-gray-900 dark:text-white">{{ $selectedBorrowing['nama'] ?? '-' }}</div>
                                    <div class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400"><i class="fa-solid fa-phone mr-1"></i>{{ $selectedBorrowing['no_hp'] ?? '-' }}</div>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800">
                                    <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Waktu Pengajuan</div>
                                    <div class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $selectedBorrowing['tanggal_pengajuan'] ?? '-' }}</div>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800">
                                    <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Status Utama</div>
                                    <div class="mt-2"><span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold {{ $this->statusBadgeClass($selectedBorrowing['status'] ?? '') }}">{{ $selectedBorrowing['status'] ?? '-' }}</span></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div class="rounded-2xl bg-indigo-50 p-4 dark:bg-indigo-500/10">
                                    <div class="text-[10px] font-bold uppercase tracking-wide text-indigo-500">Tujuan / Keperluan</div>
                                    <div class="mt-1 break-words text-sm font-semibold leading-relaxed text-slate-700 dark:text-slate-200">{{ $selectedBorrowing['tujuan'] ?? '-' }}</div>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800">
                                    <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Catatan Penggunaan</div>
                                    <div class="mt-1 break-words text-sm font-semibold leading-relaxed text-slate-700 dark:text-slate-200">{{ $selectedBorrowing['catatan'] ?? '-' }}</div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800">
                                    <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Waktu Peminjaman</div>
                                    <div class="mt-1 text-xs font-bold text-gray-900 dark:text-white">{{ $selectedBorrowing['tanggal_mulai'] ?? '-' }}</div>
                                    <div class="mt-0.5 text-[10px] text-slate-400">s/d {{ $selectedBorrowing['tanggal_selesai'] ?? '-' }}</div>
                                </div>
                                <div class="rounded-2xl bg-amber-50 p-4 dark:bg-amber-900/20">
                                    <div class="text-[10px] font-bold uppercase tracking-wide text-amber-600">Catatan Admin</div>
                                    <div class="mt-1 break-words text-xs leading-relaxed text-amber-800 dark:text-amber-200">{{ $selectedBorrowing['catatan_admin'] ?? '-' }}</div>
                                </div>
                            </div>
                            <div class="flex flex-col gap-3 rounded-2xl bg-slate-50 p-4 dark:bg-slate-800 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Lampiran</div>
                                    <div class="mt-1 text-xs font-semibold text-slate-700 dark:text-slate-200">{{ !empty($selectedBorrowing['file_lampiran'] ?? null) ? 'Tersedia' : 'Tidak ada' }}</div>
                                </div>
                                @if(!empty($selectedBorrowing['file_lampiran'] ?? null))
                                <a href="{{ Storage::url($selectedBorrowing['file_lampiran']) }}" target="_blank" rel="noopener" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-100 px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 sm:w-auto"><i class="fa-solid fa-arrow-up-right-from-square"></i>Lihat Lampiran</a>
                                @endif
                            </div>
                            <div>
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div class="text-xs font-bold text-gray-700 dark:text-gray-300">Rincian Ruangan / Barang</div>
                                    <div class="text-[10px] font-medium text-slate-400">{{ count($selectedBorrowing['details'] ?? []) }} detail</div>
                                </div>
                                <div class="hidden overflow-hidden rounded-2xl border border-slate-200 dark:border-gray-800 md:block">
                                    <div class="overflow-x-auto">
                                        <table class="w-full min-w-[1150px] text-xs text-left">
                                            <thead class="bg-slate-50 dark:bg-slate-800">
                                                <tr class="border-b border-slate-200 dark:border-gray-800">
                                                    <th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Tipe</th>
                                                    <th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Nama</th>
                                                    <th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Fasilitas</th>
                                                    <th class="px-4 py-3 text-center text-[9px] font-extrabold uppercase text-slate-400">Jumlah</th>
                                                    <th class="px-4 py-3 text-center text-[9px] font-extrabold uppercase text-slate-400">Status Item</th>
                                                    <th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Catatan Item</th>
                                                    <th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Catatan Pengembalian</th>
                                                    <th class="px-4 py-3 text-center text-[9px] font-extrabold uppercase text-slate-400">Bukti</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-gray-800">
                                                @forelse($selectedBorrowing['details'] ?? [] as $detail)
                                                <tr class="align-top">
                                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $detail['type'] }}</td>
                                                    <td class="px-4 py-3"><div class="font-bold text-gray-900 dark:text-white">{{ $detail['name'] }}</div><div class="text-[9px] text-indigo-500">#{{ $detail['code'] }}</div></td>
                                                    <td class="px-4 py-3"><div class="flex max-w-[260px] flex-wrap gap-1">@forelse($detail['fasilitas'] ?? [] as $facility)<span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-[9px] font-semibold text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"><i class="fa-solid {{ $this->facilityIcon($facility) }} text-[8px]"></i>{{ $facility }}</span>@empty<span class="text-[9px] text-slate-400">-</span>@endforelse</div></td>
                                                    <td class="px-4 py-3 text-center font-bold text-gray-900 dark:text-white">{{ $detail['jumlah'] }}</td>
                                                    <td class="px-4 py-3 text-center"><span class="inline-flex rounded-full px-2.5 py-1 text-[9px] font-bold {{ $this->statusBadgeClass($detail['status']) }}">{{ $detail['status'] }}</span></td>
                                                    <td class="max-w-[220px] px-4 py-3 text-slate-600 dark:text-slate-300">{{ $detail['catatan'] ?: '-' }}</td>
                                                    <td class="max-w-[220px] px-4 py-3 text-slate-600 dark:text-slate-300">{{ $detail['catatan_pengembalian'] ?: '-' }}</td>
                                                    <td class="px-4 py-3 text-center">@if(!empty($detail['file_bukti_pengembalian']))<a href="{{ Storage::url($detail['file_bukti_pengembalian']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg bg-indigo-100 px-2.5 py-1.5 text-[10px] font-bold text-indigo-700 hover:bg-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300"><i class="fa-solid fa-arrow-up-right-from-square"></i>Buka</a>@else<span class="text-slate-400">-</span>@endif</td>
                                                </tr>
                                                @empty
                                                <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Tidak ada rincian peminjaman.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="space-y-3 md:hidden">
                                    @forelse($selectedBorrowing['details'] ?? [] as $detail)
                                    <details class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-gray-800 dark:bg-gray-900" open>
                                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                                            <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="rounded-md bg-slate-100 px-2 py-1 text-[8px] font-extrabold uppercase text-slate-500 dark:bg-gray-800 dark:text-slate-400">{{ $detail['type'] }}</span><span class="text-[9px] font-semibold text-indigo-500">#{{ $detail['code'] }}</span></div><div class="mt-1 text-xs font-extrabold text-gray-900 dark:text-white">{{ $detail['name'] }}</div></div>
                                            <i class="fa-solid fa-chevron-down shrink-0 text-[10px] text-slate-400"></i>
                                        </summary>
                                        <div class="grid grid-cols-2 gap-3 border-t border-slate-100 p-4 dark:border-gray-800">
                                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-gray-800/70"><div class="text-[8px] font-extrabold uppercase text-slate-400">Jumlah</div><div class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $detail['jumlah'] }}</div></div>
                                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-gray-800/70"><div class="text-[8px] font-extrabold uppercase text-slate-400">Status</div><div class="mt-1"><span class="inline-flex rounded-full px-2 py-1 text-[8px] font-bold {{ $this->statusBadgeClass($detail['status']) }}">{{ $detail['status'] }}</span></div></div>
                                            <div class="col-span-2 rounded-xl bg-slate-50 p-3 dark:bg-gray-800/70"><div class="mb-1.5 text-[8px] font-extrabold uppercase text-slate-400">Fasilitas</div><div class="flex flex-wrap gap-1.5">@forelse($detail['fasilitas'] ?? [] as $facility)<span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-[8px] font-semibold text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"><i class="fa-solid {{ $this->facilityIcon($facility) }} text-[8px]"></i>{{ $facility }}</span>@empty<span class="text-[9px] text-slate-400">-</span>@endforelse</div></div>
                                            <div class="col-span-2 rounded-xl bg-slate-50 p-3 dark:bg-gray-800/70"><div class="mb-1.5 text-[8px] font-extrabold uppercase text-slate-400">Catatan Item</div><div class="break-words text-[10px] leading-relaxed text-slate-600 dark:text-slate-300">{{ $detail['catatan'] ?: '-' }}</div></div>
                                            <div class="col-span-2 rounded-xl bg-slate-50 p-3 dark:bg-gray-800/70"><div class="mb-1.5 text-[8px] font-extrabold uppercase text-slate-400">Catatan Pengembalian</div><div class="break-words text-[10px] leading-relaxed text-slate-600 dark:text-slate-300">{{ $detail['catatan_pengembalian'] ?: '-' }}</div></div>
                                            <div class="col-span-2 rounded-xl bg-slate-50 p-3 dark:bg-gray-800/70"><div class="mb-1.5 text-[8px] font-extrabold uppercase text-slate-400">Bukti Pengembalian</div>@if(!empty($detail['file_bukti_pengembalian']))<a href="{{ Storage::url($detail['file_bukti_pengembalian']) }}" target="_blank" rel="noopener" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-indigo-100 px-2.5 py-2 text-[10px] font-bold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"><i class="fa-solid fa-arrow-up-right-from-square"></i>Buka Bukti</a>@else<span class="text-[10px] text-slate-400">Tidak ada</span>@endif</div>
                                        </div>
                                    </details>
                                    @empty
                                    <div class="rounded-2xl border border-slate-200 p-8 text-center text-[10px] text-slate-400 dark:border-gray-800">Tidak ada rincian peminjaman.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="shrink-0 border-t border-gray-100 bg-white/95 p-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 sm:px-6">
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button type="button" wire:click="closeBorrowingDetailModal" class="w-full rounded-xl bg-gray-100 px-5 py-3 text-xs font-bold text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 sm:w-auto">Tutup</button>
                            <button type="button" wire:click="downloadApprovalPdf({{ $selectedBorrowing['id'] ?? 0 }})" wire:loading.attr="disabled" wire:target="downloadApprovalPdf" class="w-full rounded-xl bg-emerald-600 px-5 py-3 text-xs font-bold text-white hover:bg-emerald-700 sm:w-auto"><i class="mr-1 fa-solid fa-file-pdf"></i>Download PDF</button>
                            <button type="button" wire:click="openEditModal({{ $selectedBorrowing['id'] ?? 0 }})" class="w-full rounded-xl bg-amber-500 px-5 py-3 text-xs font-bold text-white hover:bg-amber-600 sm:w-auto"><i class="mr-1 fa-solid fa-pen-to-square"></i>Edit Data</button>
                            @if(($selectedBorrowing['status'] ?? '') === 'Menunggu')
                            <button type="button" wire:click="openApprovalModal({{ $selectedBorrowing['id'] ?? 0 }})" class="w-full rounded-xl bg-indigo-600 px-5 py-3 text-xs font-bold text-white hover:bg-indigo-700 sm:w-auto"><i class="mr-1 fa-solid fa-gavel"></i>Approve</button>
                            @elseif(($selectedBorrowing['status'] ?? '') === 'Disetujui')
                            <button type="button" wire:click="openApprovalModal({{ $selectedBorrowing['id'] ?? 0 }})" class="w-full rounded-xl bg-slate-600 px-5 py-3 text-xs font-bold text-white hover:bg-slate-700 sm:w-auto"><i class="mr-1 fa-solid fa-rotate-left"></i>Tindak Lanjut</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </section>

    <section x-data="{ open: @entangle('isApprovalModalOpen') }">
        <template x-teleport="body">
            <div x-show="open" x-cloak class="fixed inset-0 z-[9993] flex items-center justify-center p-3 sm:p-4">
                <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm"></div>
                <div x-show="open" x-transition class="relative z-[201] flex w-full max-w-6xl max-h-[95vh] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900 sm:rounded-3xl">
                    <div class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-gray-100 bg-white/95 px-4 py-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 sm:px-6 sm:py-5">
                        <div class="min-w-0"><h4 class="text-base font-bold text-gray-900 dark:text-white sm:text-lg">{{ $approvalCurrentStatus === 'Menunggu' ? 'Approve Pengajuan' : 'Tindak Lanjut Peminjaman' }}</h4><p class="mt-1 truncate text-[10px] font-bold text-indigo-600 dark:text-indigo-400 sm:text-xs">{{ $approvalTransactionCode }}</p></div>
                        <button type="button" wire:click="closeApprovalModal" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-500 hover:text-red-500 dark:bg-gray-800"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <form wire:submit="saveApproval" class="flex min-h-0 flex-1 flex-col">
                        <div class="min-h-0 flex-1 overflow-y-auto scrollbar-hidden">
                            @php($selectedApproval = !empty($approvalBorrowingId) ? \App\Models\Borrowing::with('user')->find($approvalBorrowingId) : null)
                            <div class="space-y-5 p-4 sm:p-6">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                    <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><div class="text-[9px] font-extrabold uppercase tracking-wide text-slate-400">Peminjam</div><div class="mt-1 truncate text-sm font-bold text-gray-900 dark:text-white">{{ $selectedApproval?->user?->name ?? '-' }}</div></div>
                                    <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><div class="text-[9px] font-extrabold uppercase tracking-wide text-slate-400">Waktu Pengajuan</div><div class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ optional($selectedApproval?->created_at)->format('d M Y H:i') ?? '-' }}</div></div>
                                    <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><div class="text-[9px] font-extrabold uppercase tracking-wide text-slate-400">Mulai</div><div class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ optional($selectedApproval?->tanggal_mulai)->format('d M Y H:i') ?? '-' }}</div></div>
                                    <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><div class="text-[9px] font-extrabold uppercase tracking-wide text-slate-400">Selesai</div><div class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ optional($selectedApproval?->tanggal_selesai)->format('d M Y H:i') ?? '-' }}</div></div>
                                </div>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div class="rounded-2xl bg-indigo-50 p-4 dark:bg-indigo-500/10"><div class="text-[9px] font-extrabold uppercase tracking-wide text-indigo-500">Tujuan</div><div class="mt-1 break-words text-sm font-semibold leading-relaxed text-slate-700 dark:text-slate-200">{{ $selectedApproval?->tujuan ?? '-' }}</div></div>
                                    <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><div class="text-[9px] font-extrabold uppercase tracking-wide text-slate-400">Catatan Penggunaan</div><div class="mt-1 break-words text-sm font-semibold leading-relaxed text-slate-700 dark:text-slate-200">{{ $catatan ?: '-' }}</div></div>
                                </div>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-[9px] font-extrabold uppercase tracking-wide text-slate-500">Status Utama</label>
                                        <select wire:model.live="approvalStatus" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 dark:border-slate-700 dark:bg-gray-800 dark:text-white {{ $this->statusSelectClasses($approvalStatus) }}">
                                            @foreach($this->approvalStatusOptions() as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach
                                        </select>
                                        @error('approvalStatus')<span class="mt-1 block text-[9px] text-rose-500">{{ $message }}</span>@enderror
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-[9px] font-extrabold uppercase tracking-wide text-slate-500">Catatan Admin</label>
                                        <textarea wire:model="catatan_admin" rows="3" placeholder="Catatan admin transaksi..." class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 dark:border-slate-700 dark:bg-gray-800 dark:text-white"></textarea>
                                        @error('catatan_admin')<span class="mt-1 block text-[9px] text-rose-500">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-gray-800">
                                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-800"><div class="text-xs font-extrabold text-slate-700 dark:text-slate-200">Rincian Peminjaman</div><div class="mt-0.5 text-[9px] text-slate-400">{{ $approvalCurrentStatus === 'Menunggu' ? 'Kelola fasilitas, catatan, jumlah, dan status per detail.' : 'Kelola data pengembalian per detail.' }}</div></div>
                                    <div class="hidden overflow-x-auto md:block">
                                        <table class="w-full min-w-[1320px] text-xs text-left">
                                            <thead><tr class="border-b border-slate-200 dark:border-gray-800"><th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Tipe</th><th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Nama</th><th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Fasilitas</th><th class="px-4 py-3 text-center text-[9px] font-extrabold uppercase text-slate-400">Jumlah</th><th class="px-4 py-3 text-center text-[9px] font-extrabold uppercase text-slate-400">Status Item</th><th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Catatan Item</th>@if($approvalCurrentStatus !== 'Menunggu')<th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Catatan Pengembalian</th><th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Bukti Pengembalian</th>@endif</tr></thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-gray-800">
                                                @forelse($approvalDetails as $index => $detail)
                                                <tr class="align-top">
                                                    <td class="px-4 py-4 text-slate-500">{{ $detail['type'] }}</td>
                                                    <td class="px-4 py-4"><div class="font-bold text-gray-900 dark:text-white">{{ $detail['name'] }}</div><div class="text-[9px] text-indigo-500">#{{ $detail['code'] }}</div></td>
                                                    <td class="px-4 py-4"><div class="flex max-w-[300px] flex-wrap gap-1.5">@forelse($detail['available_fasilitas'] ?? [] as $facility)@php($selected=in_array($facility,$detail['fasilitas']??[],true))@if($approvalCurrentStatus === 'Menunggu')<label class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-[9px] font-semibold {{ $selected ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/50 dark:bg-indigo-500/10 dark:text-indigo-300' : 'border-slate-200 bg-white text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400' }}"><input type="checkbox" wire:model.live="approvalDetails.{{ $index }}.fasilitas" value="{{ $facility }}" class="h-3 w-3 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"><i class="fa-solid {{ $this->facilityIcon($facility) }} text-[9px] text-indigo-500"></i>{{ $facility }}</label>@elseif($selected)<span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-[9px] font-semibold {{ $this->facilityStatusClasses('Disetujui') }}"><i class="fa-solid {{ $this->facilityIcon($facility) }} text-[9px]"></i>{{ $facility }}</span>@endif @empty<span class="text-[9px] text-slate-400">-</span>@endforelse</div></td>
                                                    <td class="px-4 py-4 text-center">@if(($detail['item_id'] ?? null) !== null)<input type="number" min="1" wire:model="approvalDetails.{{ $index }}.jumlah" class="w-20 rounded-lg border border-slate-200 bg-slate-50 px-2 py-2 text-center text-xs dark:border-slate-700 dark:bg-gray-800 dark:text-white">@else<span class="font-bold text-gray-900 dark:text-white">1</span>@endif</td>
                                                    <td class="px-4 py-4"><select wire:model.live="approvalDetails.{{ $index }}.status" class="min-w-[145px] rounded-xl border px-3 py-2 text-[10px] font-bold outline-none {{ $this->statusSelectClasses($detail['status']) }}">@foreach($this->detailStatusOptions() as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select></td>
                                                    <td class="px-4 py-4"><textarea wire:model="approvalDetails.{{ $index }}.catatan" rows="2" {{ $approvalCurrentStatus !== 'Menunggu' ? 'readonly' : '' }} placeholder="Catatan item..." class="w-52 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-[10px] dark:border-slate-700 dark:bg-gray-800 dark:text-white"></textarea></td>
                                                    @if($approvalCurrentStatus !== 'Menunggu')
                                                    <td class="px-4 py-4"><textarea wire:model="approvalDetails.{{ $index }}.catatan_pengembalian" rows="2" placeholder="Catatan kondisi pengembalian..." class="w-52 rounded-xl border border-slate-200 bg-white px-3 py-2 text-[10px] dark:border-slate-700 dark:bg-gray-900 dark:text-white"></textarea></td>
                                                    <td class="px-4 py-4"><input type="file" data-compress-return wire:model="returnUploads.{{ $index }}" accept="application/pdf,image/*" capture="environment" class="block w-52 text-[9px] text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-[9px] file:font-bold file:text-indigo-700"><div class="mt-1 text-[8px] text-slate-400">Opsional · maks. 1 MB</div>@if(!empty($detail['file_bukti_pengembalian']))<a href="{{ Storage::url($detail['file_bukti_pengembalian']) }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-[9px] font-bold text-slate-600 dark:bg-gray-800 dark:text-slate-300"><i class="fa-solid fa-paperclip"></i>Bukti saat ini</a>@endif @error('returnUploads.'.$index)<span class="mt-1 block text-[9px] text-rose-500">{{ $message }}</span>@enderror</td>
                                                    @endif
                                                </tr>
                                                @empty<tr><td colspan="{{ $approvalCurrentStatus === 'Menunggu' ? 6 : 8 }}" class="px-4 py-10 text-center text-slate-400">Tidak ada rincian peminjaman.</td></tr>@endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="space-y-3 p-3 md:hidden">
                                        @forelse($approvalDetails as $index => $detail)
                                        <details wire:key="approval-detail-{{ $detail['id'] }}" class="overflow-hidden rounded-2xl border border-slate-200 dark:border-gray-800" open>
                                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                                                <div class="min-w-0"><div class="flex flex-wrap gap-2"><span class="rounded-md bg-slate-100 px-2 py-1 text-[8px] font-extrabold uppercase text-slate-500 dark:bg-gray-800 dark:text-slate-400">{{ $detail['type'] }}</span><span class="text-[9px] font-semibold text-indigo-500">#{{ $detail['code'] }}</span></div><div class="mt-1 text-xs font-extrabold text-gray-900 dark:text-white">{{ $detail['name'] }}</div></div>
                                                <i class="fa-solid fa-chevron-down shrink-0 text-[10px] text-slate-400"></i>
                                            </summary>
                                            <div class="grid grid-cols-1 gap-3 border-t border-slate-100 p-4 dark:border-gray-800">
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-gray-800/70"><div class="text-[8px] font-extrabold uppercase text-slate-400">Jumlah</div>@if(($detail['item_id'] ?? null) !== null)<input type="number" min="1" wire:model="approvalDetails.{{ $index }}.jumlah" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold dark:border-slate-700 dark:bg-gray-900 dark:text-white">@else<div class="mt-1 text-sm font-bold text-gray-900 dark:text-white">1</div>@endif</div>
                                                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-gray-800/70"><div class="text-[8px] font-extrabold uppercase text-slate-400">Status Item</div><select wire:model.live="approvalDetails.{{ $index }}.status" class="mt-1 w-full rounded-xl border px-3 py-2 text-[10px] font-bold {{ $this->statusSelectClasses($detail['status']) }}">@foreach($this->detailStatusOptions() as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select></div>
                                                </div>
                                                <div class="rounded-xl bg-slate-50 p-3 dark:bg-gray-800/70"><div class="mb-1.5 text-[8px] font-extrabold uppercase text-slate-400">Fasilitas</div><div class="flex flex-wrap gap-1.5">@forelse($detail['available_fasilitas'] ?? [] as $facility)@php($selected=in_array($facility,$detail['fasilitas']??[],true))@if($approvalCurrentStatus === 'Menunggu')<label class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-[8px] font-semibold {{ $selected ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-200 bg-white text-slate-500' }}"><input type="checkbox" wire:model.live="approvalDetails.{{ $index }}.fasilitas" value="{{ $facility }}" class="h-3 w-3 rounded text-indigo-600"><i class="fa-solid {{ $this->facilityIcon($facility) }} text-[8px] text-indigo-500"></i>{{ $facility }}</label>@elseif($selected)<span class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[8px] font-semibold {{ $this->facilityStatusClasses('Disetujui') }}"><i class="fa-solid {{ $this->facilityIcon($facility) }} text-[8px]"></i>{{ $facility }}</span>@endif @empty<span class="text-[9px] text-slate-400">Tidak ada fasilitas.</span>@endforelse</div></div>
                                                <div><label class="mb-1 block text-[8px] font-extrabold uppercase text-slate-400">Catatan Item</label><textarea wire:model="approvalDetails.{{ $index }}.catatan" rows="3" {{ $approvalCurrentStatus !== 'Menunggu' ? 'readonly' : '' }} class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-[10px] dark:border-slate-700 dark:bg-gray-800 dark:text-white"></textarea></div>
                                                @if($approvalCurrentStatus !== 'Menunggu')
                                                <div><label class="mb-1 block text-[8px] font-extrabold uppercase text-slate-400">Catatan Pengembalian</label><textarea wire:model="approvalDetails.{{ $index }}.catatan_pengembalian" rows="3" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-[10px] dark:border-slate-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                                                <div><label class="mb-1 block text-[8px] font-extrabold uppercase text-slate-400">Bukti Pengembalian</label><input type="file" wire:model="returnUploads.{{ $index }}" accept="application/pdf,image/*" capture="environment" class="block w-full text-[9px] text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:font-bold file:text-indigo-700">@if(!empty($detail['file_bukti_pengembalian']))<a href="{{ Storage::url($detail['file_bukti_pengembalian']) }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-[9px] font-bold text-slate-600 dark:bg-gray-800 dark:text-slate-300"><i class="fa-solid fa-paperclip"></i>Bukti saat ini</a>@endif @error('returnUploads.'.$index)<span class="mt-1 block text-[9px] text-rose-500">{{ $message }}</span>@enderror</div>
                                                @endif
                                            </div>
                                        </details>
                                        @empty<div class="rounded-2xl border border-slate-200 p-8 text-center text-slate-400 dark:border-gray-800">Tidak ada rincian peminjaman.</div>@endforelse
                                    </div>
                                </div>
                            </div>
                            @if($approvalCurrentStatus !== 'Menunggu')
                            <div class="mx-4 mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-gray-800 dark:bg-gray-800/60">
                                <div class="text-[9px] font-extrabold uppercase tracking-wide text-slate-500">Catatan Peminjaman</div>
                                <div class="mt-1 break-words text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ $catatan ?: '-' }}</div>
                            </div>
                            @endif
                        </div>
                        <div class="shrink-0 border-t border-gray-100 bg-white/95 p-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 sm:px-6">
                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" wire:click="closeApprovalModal" class="w-full rounded-xl bg-slate-100 px-5 py-3 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300 sm:w-auto">Tutup</button><button type="submit" wire:loading.attr="disabled" wire:target="saveApproval,returnUploads" class="w-full rounded-xl bg-indigo-600 px-5 py-3 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50 sm:w-auto"><span wire:loading.remove wire:target="saveApproval">{{ $approvalCurrentStatus === 'Menunggu' ? 'Simpan Approval' : 'Simpan Tindak Lanjut' }}</span><span wire:loading wire:target="saveApproval"><i class="mr-1 fa-solid fa-spinner animate-spin"></i>Menyimpan...</span></button></div>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </section>

    <section x-data="{ open: @entangle('isEditModalOpen') }">
        <template x-teleport="body">
            <div x-show="open" x-cloak class="fixed inset-0 z-[9994] flex items-center justify-center p-3 sm:p-4">
                <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm"></div>
                <div x-show="open" x-transition class="relative z-[211] flex w-full max-w-6xl max-h-[95vh] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900 sm:rounded-3xl">
                    <div class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-gray-100 bg-white/95 px-4 py-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 sm:px-6 sm:py-5">
                        <div class="min-w-0"><h4 class="text-base font-bold text-gray-900 dark:text-white sm:text-lg">Edit Data Peminjaman</h4><p class="mt-1 truncate text-[12px] font-bold text-indigo-600 dark:text-indigo-400">{{ $editForm['kode_transaksi'] ?? '-' }}</p></div>
                        <button type="button" wire:click="closeEditModal" class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-gray-800"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <form wire:submit="saveEdit" class="flex min-h-0 flex-1 flex-col">
                        <div class="min-h-0 flex-1 overflow-y-auto scrollbar-hidden">
                            <div class="space-y-5 p-4 sm:p-6">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div><label class="mb-2 block text-[9px] font-extrabold uppercase tracking-wide text-slate-500">Peminjam</label><select wire:model="editForm.user_id" class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm dark:bg-gray-800 dark:text-white"><option value="">-- Pilih Peminjam --</option>@foreach($usersList as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>@endforeach</select>@error('editForm.user_id')<span class="mt-1 block text-[9px] text-rose-500">{{ $message }}</span>@enderror</div>
                                    <div><label class="mb-2 block text-[9px] font-extrabold uppercase tracking-wide text-slate-500">Status Utama</label><select wire:model="editForm.status" class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm font-bold dark:bg-gray-800 dark:text-white {{ $this->statusSelectClasses($editForm['status']) }}">@foreach($statusOptions as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select>@error('editForm.status')<span class="mt-1 block text-[9px] text-rose-500">{{ $message }}</span>@enderror</div>
                                    <div><label class="mb-2 block text-[9px] font-extrabold uppercase tracking-wide text-slate-500">Mulai</label><input type="datetime-local" wire:model="editForm.tanggal_mulai" class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm dark:bg-gray-800 dark:text-white"></div>
                                    <div><label class="mb-2 block text-[9px] font-extrabold uppercase tracking-wide text-slate-500">Selesai</label><input type="datetime-local" wire:model="editForm.tanggal_selesai" class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm dark:bg-gray-800 dark:text-white"></div>
                                </div>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div><label class="mb-2 block text-[9px] font-extrabold uppercase tracking-wide text-slate-500">Tujuan / Keperluan</label><textarea wire:model="editForm.tujuan" rows="4" class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm dark:bg-gray-800 dark:text-white" placeholder="Tujuan / Keperluan"></textarea></div>
                                    <div><label class="mb-2 block text-[9px] font-extrabold uppercase tracking-wide text-slate-500">Catatan Peminjaman</label><textarea wire:model="editForm.catatan" rows="4" class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm dark:bg-gray-800 dark:text-white" placeholder="Catatan untuk peminjam"></textarea></div>
                                </div>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div><label class="mb-2 block text-[9px] font-extrabold uppercase tracking-wide text-slate-500">Catatan Admin</label><textarea wire:model="editForm.catatan_admin" rows="4" class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm dark:bg-gray-800 dark:text-white" placeholder="Catatan untuk admin"></textarea></div>
                                    <div><label class="mb-2 block text-[9px] font-extrabold uppercase tracking-wide text-slate-500">File Lampiran</label><input type="file" wire:model="editFile" data-compress-return accept="application/pdf,image/jpeg,image/png,image/webp" class="block w-full text-[10px] text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-[10px] file:font-bold file:text-indigo-700"><div wire:loading wire:target="editFile" class="mt-1 text-[9px] text-indigo-600">Memproses file...</div>@if($editFile)<div class="mt-1 text-[9px] font-semibold text-emerald-600">{{ $editFile->getClientOriginalName() }}</div>@endif @error('editFile')<span class="mt-1 block text-[9px] text-rose-500">{{ $message }}</span>@enderror</div>
                                </div>
                                <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-gray-800">
                                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-800"><div class="text-xs font-extrabold text-slate-700 dark:text-slate-200">Rincian Peminjaman</div><div class="mt-0.5 text-[9px] text-slate-400">Seluruh detail dapat disesuaikan.</div></div>
                                    <div class="hidden overflow-x-auto md:block">
                                        <table class="w-full min-w-[1400px] text-xs text-left">
                                            <thead><tr class="border-b border-slate-200 dark:border-gray-800"><th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Tipe</th><th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Nama</th><th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Fasilitas</th><th class="px-4 py-3 text-center text-[9px] font-extrabold uppercase text-slate-400">Jumlah</th><th class="px-4 py-3 text-center text-[9px] font-extrabold uppercase text-slate-400">Status</th><th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Catatan Item</th><th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Catatan Pengembalian</th><th class="px-4 py-3 text-[9px] font-extrabold uppercase text-slate-400">Bukti Pengembalian</th></tr></thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-gray-800">
                                                @forelse($editDetails as $index => $detail)
                                                <tr class="align-top">
                                                    <td class="px-4 py-4 text-slate-500">{{ $detail['type'] }}</td>
                                                    <td class="px-4 py-4"><div class="font-bold text-gray-900 dark:text-white">{{ $detail['name'] }}</div><div class="text-[9px] text-indigo-500">#{{ $detail['code'] }}</div></td>
                                                    <td class="px-4 py-4"><div class="flex max-w-[310px] flex-wrap gap-1.5">@forelse($detail['available_fasilitas'] ?? [] as $facility)@php($selected=in_array($facility,$detail['fasilitas']??[],true))<label class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-[9px] font-semibold {{ $selected ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-200 bg-white text-slate-500' }}"><input type="checkbox" wire:model.live="editDetails.{{ $index }}.fasilitas" value="{{ $facility }}" class="h-3 w-3 rounded text-indigo-600"><i class="fa-solid {{ $this->facilityIcon($facility) }} text-[9px] text-indigo-500"></i>{{ $facility }}</label>@empty<span class="text-[9px] text-slate-400">Tidak ada fasilitas.</span>@endforelse</div></td>
                                                    <td class="px-4 py-4 text-center">@if(($detail['item_id'] ?? null) !== null)<input type="number" min="1" wire:model="editDetails.{{ $index }}.jumlah" class="w-20 rounded-lg border border-slate-200 bg-slate-50 px-2 py-2 text-center text-xs dark:border-slate-700 dark:bg-gray-800 dark:text-white">@else<span class="font-bold text-gray-900 dark:text-white">1</span>@endif</td>
                                                    <td class="px-4 py-4"><select wire:model.live="editDetails.{{ $index }}.status" class="min-w-[145px] rounded-xl border px-3 py-2 text-[10px] font-bold {{ $this->statusSelectClasses($detail['status']) }}">@foreach($statusOptions as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select></td>
                                                    <td class="px-4 py-4"><textarea wire:model="editDetails.{{ $index }}.catatan" rows="3" placeholder="Catatan item..." class="w-56 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-[10px] dark:border-slate-700 dark:bg-gray-800 dark:text-white"></textarea></td>
                                                    <td class="px-4 py-4"><textarea wire:model="editDetails.{{ $index }}.catatan_pengembalian" rows="3" placeholder="Catatan kondisi pengembalian..." class="w-56 rounded-xl border border-slate-200 bg-white px-3 py-2 text-[10px] dark:border-slate-700 dark:bg-gray-900 dark:text-white"></textarea></td>
                                                    <td class="px-4 py-4"><input type="file" data-compress-return wire:model="returnUploads.{{ $index }}" accept="application/pdf,image/*" capture="environment" class="block w-52 text-[9px] text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-[9px] file:font-bold file:text-indigo-700"><div class="mt-1 text-[8px] text-slate-400">Opsional · maks. 1 MB</div>@if(!empty($detail['file_bukti_pengembalian']))<a href="{{ Storage::url($detail['file_bukti_pengembalian']) }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-[9px] font-bold text-slate-600 dark:bg-gray-800 dark:text-slate-300"><i class="fa-solid fa-paperclip"></i>Bukti saat ini</a>@endif @error('returnUploads.'.$index)<span class="mt-1 block text-[9px] text-rose-500">{{ $message }}</span>@enderror</td>
                                                </tr>
                                                @empty<tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Tidak ada rincian peminjaman.</td></tr>@endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="space-y-3 p-3 md:hidden">
                                        @forelse($editDetails as $index => $detail)
                                        <details wire:key="edit-detail-{{ $detail['id'] }}" class="overflow-hidden rounded-2xl border border-slate-200 dark:border-gray-800" open>
                                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3"><div class="min-w-0"><div class="text-[8px] font-extrabold uppercase text-slate-400">{{ $detail['type'] }} · #{{ $detail['code'] }}</div><div class="mt-1 text-xs font-extrabold text-gray-900 dark:text-white">{{ $detail['name'] }}</div></div><i class="fa-solid fa-chevron-down text-[10px] text-slate-400"></i></summary>
                                            <div class="space-y-3 border-t border-slate-100 p-4 dark:border-gray-800">
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-gray-800/70"><label class="mb-1 block text-[8px] font-extrabold uppercase text-slate-400">Jumlah</label>@if(($detail['item_id'] ?? null) !== null)<input type="number" min="1" wire:model="editDetails.{{ $index }}.jumlah" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold dark:border-slate-700 dark:bg-gray-900 dark:text-white">@else<div class="text-sm font-bold text-gray-900 dark:text-white">1</div>@endif</div>
                                                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-gray-800/70"><label class="mb-1 block text-[8px] font-extrabold uppercase text-slate-400">Status</label><select wire:model.live="editDetails.{{ $index }}.status" class="w-full rounded-xl border px-3 py-2 text-[10px] font-bold {{ $this->statusSelectClasses($detail['status']) }}">@foreach($statusOptions as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select></div>
                                                </div>
                                                <div class="rounded-xl bg-slate-50 p-3 dark:bg-gray-800/70"><label class="mb-1.5 block text-[8px] font-extrabold uppercase text-slate-400">Fasilitas</label><div class="flex flex-wrap gap-1.5">@foreach($detail['available_fasilitas'] ?? [] as $facility)@php($selected=in_array($facility,$detail['fasilitas']??[],true))<label class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-[8px] font-semibold {{ $selected ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-500' }}"><input type="checkbox" wire:model.live="editDetails.{{ $index }}.fasilitas" value="{{ $facility }}" class="h-3 w-3 rounded text-indigo-600"><i class="fa-solid {{ $this->facilityIcon($facility) }} text-[8px] text-indigo-500"></i>{{ $facility }}</label>@endforeach</div></div>
                                                <div><label class="mb-1 block text-[8px] font-extrabold uppercase text-slate-400">Catatan Item</label><textarea wire:model="editDetails.{{ $index }}.catatan" rows="3" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-[10px] dark:border-slate-700 dark:bg-gray-800 dark:text-white"></textarea></div>
                                                <div><label class="mb-1 block text-[8px] font-extrabold uppercase text-slate-400">Catatan Pengembalian</label><textarea wire:model="editDetails.{{ $index }}.catatan_pengembalian" rows="3" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-[10px] dark:border-slate-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                                                <div><label class="mb-1 block text-[8px] font-extrabold uppercase text-slate-400">Bukti Pengembalian</label><input type="file" wire:model="returnUploads.{{ $index }}" data-compress-return accept="application/pdf,image/*" capture="environment" class="block w-full text-[9px] text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2.5 file:font-bold file:text-indigo-700">@if(!empty($detail['file_bukti_pengembalian']))<a href="{{ Storage::url($detail['file_bukti_pengembalian']) }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-[9px] font-bold text-slate-600 dark:bg-gray-800 dark:text-slate-300"><i class="fa-solid fa-paperclip"></i>Bukti saat ini</a>@endif @error('returnUploads.'.$index)<span class="mt-1 block text-[9px] text-rose-500">{{ $message }}</span>@enderror</div>
                                            </div>
                                        </details>
                                        @empty<div class="rounded-2xl border border-slate-200 p-8 text-center text-slate-400 dark:border-gray-800">Tidak ada rincian peminjaman.</div>@endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="shrink-0 border-t border-gray-100 bg-white/95 p-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 sm:px-6">
                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" wire:click="closeEditModal" class="w-full rounded-xl bg-slate-100 px-5 py-3 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300 sm:w-auto">Batal</button><button type="submit" wire:loading.attr="disabled" wire:target="saveEdit,editFile,returnUploads" class="w-full rounded-xl bg-indigo-600 px-5 py-3 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50 sm:w-auto"><span wire:loading.remove wire:target="saveEdit">Simpan Perubahan</span><span wire:loading wire:target="saveEdit"><i class="mr-1 fa-solid fa-spinner animate-spin"></i>Menyimpan...</span></button></div>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </section>
    <section x-data="{ open: @entangle('isAddModalOpen') }">
        <template x-teleport="body">
            <div x-show="open" x-cloak class="fixed inset-0 z-[9994] flex items-center justify-center p-3 sm:p-4">
                <div class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm"></div>
                <div x-show="open" x-transition
                    class="relative z-[81] flex w-full max-w-5xl max-h-[94vh] flex-col overflow-hidden rounded-2xl sm:rounded-3xl bg-white shadow-2xl dark:bg-gray-900">
                    <div
                        class="sticky top-0 z-30 flex shrink-0 items-center justify-between gap-3 px-4 py-4 sm:px-6 sm:py-5 bg-white/95 border-b border-gray-100 dark:bg-gray-900/95 dark:border-gray-800 backdrop-blur">
                        <h4 class="text-lg font-bold text-gray-900 sm:text-xl dark:text-white">Tambah Booking</h4>
                        <button type="button" wire:click="closeAddModal" aria-label="Tutup modal"
                            class="flex items-center justify-center w-9 h-9 shrink-0 text-gray-500 rounded-xl bg-gray-100 hover:bg-red-50 hover:text-red-500 dark:bg-gray-800 dark:hover:bg-red-900/20">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <form wire:submit="saveBooking" class="flex min-h-0 flex-1 flex-col">
                        <div class="hide-scrollbar scrollbar-hidden min-h-0 flex-1 overflow-y-auto">
                            <div class="p-4 space-y-6 sm:p-6">
                                @if($errors->has('form.user_id'))
                                <div
                                    class="p-3 text-xs font-medium text-red-700 border border-red-200 rounded-xl bg-red-50 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800">
                                    {{ $errors->first('form.user_id') }}
                                </div>
                                @endif

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div class="md:col-span-3">
                                        <label
                                            class="block mb-2 text-xs font-bold text-gray-700 dark:text-gray-300">Peminjam
                                            / User</label>
                                        <div wire:ignore x-data="{ init() {
                                            const el = $(this.$refs.selectUser).select2({
                                                placeholder: '-- Pilih Peminjam --',
                                                width: '100%',
                                                dropdownParent: $('body'),
                                                dropdownAutoWidth: false
                                            });
                                            el.on('change', e => $wire.set('form.user_id', e.target.value));
                                            $watch('$wire.form.user_id', value => {
                                                if (el.val() !== value) el.val(value).trigger('change.select2');
                                            });
                                        }}" class="w-full">
                                            <select x-ref="selectUser"
                                                class="w-full px-4 py-3 text-sm border-none bg-slate-50 rounded-xl dark:bg-gray-800 dark:text-white">
                                                <option value="">-- Pilih Peminjam --</option>
                                                @foreach($usersList as $user)
                                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})
                                                </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @error('form.user_id')
                                        <span class="block mt-1 text-xs text-red-500">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div>
                                        <label
                                            class="block mb-2 text-xs font-bold text-gray-700 dark:text-gray-300">Mulai</label>
                                        <input type="datetime-local" wire:model="form.tanggal_mulai"
                                            class="w-full px-4 py-3 text-sm border-none bg-slate-50 rounded-xl dark:bg-gray-800 dark:text-white md:min-w-[200px]">
                                    </div>

                                    <div>
                                        <label
                                            class="block mb-2 text-xs font-bold text-gray-700 dark:text-gray-300">Selesai</label>
                                        <input type="datetime-local" wire:model="form.tanggal_selesai"
                                            class="w-full px-4 py-3 text-sm border-none bg-slate-50 rounded-xl dark:bg-gray-800 dark:text-white">
                                    </div>

                                    <div>
                                        <label
                                            class="block mb-2 text-xs font-bold text-gray-700 dark:text-gray-300">Tujuan</label>
                                        <input type="text" wire:model="form.tujuan"
                                            placeholder="Contoh : digunakan untuk rapat"
                                            class="w-full px-4 py-3 text-sm border-none bg-slate-50 rounded-xl dark:bg-gray-800 dark:text-white">
                                    </div>
                                </div>

                                <div class="p-4 border rounded-2xl sm:p-5 border-slate-200 dark:border-gray-800">
                                    <div
                                        class="flex flex-col gap-3 mb-3 sm:flex-row sm:items-center sm:justify-between">
                                        <h5 class="font-bold text-gray-900 dark:text-white">Ruangan</h5>
                                        <button type="button" wire:click="addRoomRow"
                                            class="w-full px-3 py-2 text-xs font-bold text-indigo-600 rounded-lg sm:w-auto bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-300">
                                            + Tambah Ruangan
                                        </button>
                                    </div>

                                    <div class="space-y-3">
                                        @foreach($form['rooms'] as $index => $room)
                                        <div wire:key="admin-room-{{ $index }}"
                                            class="flex flex-col gap-3 sm:flex-row sm:items-start">
                                            <div wire:ignore x-data="{ init() {
                                                    const el = $(this.$refs.selectRoom).select2({
                                                        placeholder: '-- Pilih Ruangan --',
                                                        width: '100%',
                                                        dropdownParent: $('body'),
                                                        dropdownAutoWidth: false
                                                    });
                                                    el.on('change', e => $wire.set('form.rooms.{{ $index }}.room_id', e.target.value));
                                                    $watch('$wire.form.rooms[{{ $index }}].room_id', value => {
                                                        if (el.val() !== value) el.val(value).trigger('change.select2');
                                                    });
                                                }}" class="w-full min-w-0">
                                                <select x-ref="selectRoom"
                                                    class="w-full px-4 py-3 text-sm border-none bg-slate-50 rounded-xl dark:bg-gray-800 dark:text-white">
                                                    <option value="">-- Pilih Ruangan --</option>
                                                    @foreach($roomsList as $r)
                                                    <option value="{{ $r->id }}">{{ $r->nama_ruangan }}
                                                        (#{{ $r->kode_ruangan }})</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <button type="button" wire:click="removeRoomRow({{ $index }})"
                                                aria-label="Hapus ruangan"
                                                class="flex items-center justify-center w-full p-3 text-red-500 bg-red-50 rounded-xl sm:w-12 dark:bg-red-500/10">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="p-4 border rounded-2xl sm:p-5 border-slate-200 dark:border-gray-800">
                                    <div
                                        class="flex flex-col gap-3 mb-3 sm:flex-row sm:items-center sm:justify-between">
                                        <h5 class="font-bold text-gray-900 dark:text-white">Barang / Inventaris</h5>
                                        <button type="button" wire:click="addItemRow"
                                            class="w-full px-3 py-2 text-xs font-bold text-indigo-600 rounded-lg sm:w-auto bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-300">
                                            + Tambah Barang
                                        </button>
                                    </div>

                                    <div class="space-y-3">
                                        @foreach($form['items'] as $index => $item)
                                        <div wire:key="admin-item-{{ $index }}"
                                            class="flex flex-col gap-3 sm:flex-row sm:items-start">
                                            <div wire:ignore x-data="{ init() {
                                                    const el = $(this.$refs.selectItem).select2({
                                                        placeholder: '-- Pilih Barang --',
                                                        width: '100%',
                                                        dropdownParent: $('body'),
                                                        dropdownAutoWidth: false
                                                    });
                                                    el.on('change', e => $wire.set('form.items.{{ $index }}.item_id', e.target.value));
                                                    $watch('$wire.form.items[{{ $index }}].item_id', value => {
                                                        if (el.val() !== value) el.val(value).trigger('change.select2');
                                                    });
                                                }}" class="w-full min-w-0 sm:flex-1">
                                                <select x-ref="selectItem"
                                                    class="w-full px-4 py-3 text-sm border-none bg-slate-50 rounded-xl dark:bg-gray-800 dark:text-white">
                                                    <option value="">-- Pilih Barang --</option>
                                                    @foreach($itemsList as $it)
                                                    <option value="{{ $it->id }}">{{ $it->nama_barang }}
                                                        (#{{ $it->kode_barang }}) - Stok {{ $it->jumlah_total }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            @php($selectedStock = $this->itemStock($item['item_id'] ?? null))

                                            <div class="w-full sm:w-28 sm:shrink-0">
                                                <input type="number" min="{{ $selectedStock > 0 ? 1 : 0 }}"
                                                    max="{{ $selectedStock }}" {{ !$item['item_id'] ? 'disabled' : '' }}
                                                    wire:model.live="form.items.{{ $index }}.jumlah" placeholder="Qty"
                                                    class="w-full px-3 py-3 text-sm border-none bg-slate-50 rounded-xl dark:bg-gray-800 dark:text-white disabled:opacity-50 disabled:cursor-not-allowed">
                                                <div class="mt-1 text-[9px] text-center text-slate-400">
                                                    {{ $selectedStock > 0 ? 'Maks. '.$selectedStock : 'Pilih barang' }}
                                                </div>
                                                @error('form.items.'.$index.'.jumlah')
                                                <span class="block mt-1 text-[10px] text-red-500">{{ $message }}</span>
                                                @enderror
                                            </div>

                                            <button type="button" wire:click="removeItemRow({{ $index }})"
                                                aria-label="Hapus barang"
                                                class="flex items-center justify-center w-full p-3 text-red-500 bg-red-50 rounded-xl sm:w-12 dark:bg-red-500/10">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div>
                                    <label
                                        class="block mb-2 text-xs font-bold text-gray-700 dark:text-gray-300">Lampiran
                                        (File SP) (Opsional)</label>
                                    <input type="file" wire:model="formFile"
                                        data-compress-return accept="application/pdf,image/jpeg,image/png,image/webp"
                                        class="block w-full text-xs text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-bold file:text-indigo-700 hover:file:bg-indigo-100">
                                    <div wire:loading wire:target="formFile"
                                        class="flex items-center gap-2 mt-2 text-xs font-medium text-indigo-600">
                                        <i class="fa-solid fa-spinner animate-spin"></i> Mengunggah file...
                                    </div>
                                    @if($formFile)
                                    <div
                                        class="flex items-center gap-2 p-3 mt-2 text-xs rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300">
                                        <i class="fa-solid fa-paperclip shrink-0"></i>
                                        <span class="truncate">{{ $formFile->getClientOriginalName() }}</span>
                                    </div>
                                    @endif
                                    @error('formFile')
                                    <span class="block mt-1 text-xs text-red-500">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div
                            class="shrink-0 p-4 sm:px-6 sm:py-4 border-t border-gray-100 bg-white/95 backdrop-blur dark:bg-gray-900/95 dark:border-gray-800">
                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
                                <button type="button" wire:click="closeAddModal"
                                    class="w-full px-5 py-3 text-sm font-bold text-gray-700 bg-gray-100 rounded-xl sm:w-auto sm:py-2.5 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300">
                                    Batal
                                </button>
                                <button type="submit" wire:loading.attr="disabled" wire:target="saveBooking"
                                    class="w-full px-5 py-3 text-sm font-bold text-white bg-indigo-600 rounded-xl sm:w-auto sm:py-2.5 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="saveBooking">Simpan Booking</span>
                                    <span wire:loading wire:target="saveBooking">
                                        <i class="mr-1 fa-solid fa-spinner animate-spin"></i>Sedang menyimpan data...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </section>
    <x-toast />

    <script >
        document.addEventListener('change', async (event) => {
            const input = event.target;

            // 1. Pastikan target sesuai dan ada file
            if (!input.matches('[data-compress-return]') || !input.files?.length) return;

            // 2. Mencegah Infinite Loop akibat dispatchEvent di akhir script
            if (input.dataset.isCompressing === "true") return;

            let file = input.files[0];
            const fileName = file.name.toLowerCase();
            
            // Deteksi format HEIC (iPhone sering menganggapnya mimetype kosong atau application/octet-stream)
            const isHeic = fileName.endsWith('.heic') || fileName.endsWith('.heif') || file.type === 'image/heic' || file.type === 'image/heif';

            // Abaikan jika bukan HEIC dan juga bukan file gambar standar
            if (!isHeic && (!file.type || !file.type.startsWith('image/'))) return;

            // Jika gambar standar (bukan HEIC) dan ukurannya sudah di bawah 900KB, batalkan kompresi
            if (!isHeic && file.size <= 900 * 1024) return;

            try {
                // Kunci input agar tidak memicu kompresi berulang
                input.dataset.isCompressing = "true";

                // 3. Konversi HEIC ke JPEG jika formatnya HEIC
                if (isHeic) {
                    if (typeof heic2any === 'undefined') {
                        throw new Error("Library heic2any tidak ditemukan di halaman ini.");
                    }
                    
                    // Proses konversi HEIC ke Blob JPEG
                    const convertedBlob = await heic2any({
                        blob: file,
                        toType: "image/jpeg",
                        quality: 0.85
                    });
                    
                    // heic2any bisa mengembalikan array jika ada multiple frames, ambil yang pertama
                    const finalBlob = Array.isArray(convertedBlob) ? convertedBlob[0] : convertedBlob;
                    const newName = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                    
                    // Ganti objek file original dengan file hasil konversi
                    file = new File([finalBlob], newName, { type: 'image/jpeg' });

                    // Jika hasil konversi HEIC ukurannya langsung di bawah 900KB, lewati proses canvas
                    if (file.size <= 900 * 1024) {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        input.files = dt.files;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                        return; 
                    }
                }

                // 4. Gunakan FileReader & Image untuk kompatibilitas maksimal di Safari iOS
                const img = new Image();
                const imageLoadPromise = new Promise((resolve, reject) => {
                    img.onload = () => resolve(img);
                    img.onerror = reject;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        img.src = e.target.result;
                    };
                    reader.onerror = reject;
                    reader.readAsDataURL(file); // Membaca file (bisa jadi file original atau hasil konversi HEIC)
                });

                await imageLoadPromise;

                const maxSide = 1600;
                const scale = Math.min(1, maxSide / Math.max(img.width, img.height));

                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(img.width * scale));
                canvas.height = Math.max(1, Math.round(img.height * scale));

                // Gambar ke canvas
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                // 5. Gunakan JPEG untuk hasil akhir
                const mimeType = 'image/jpeg';
                const fileExt = '.jpg';
                let quality = 0.82;

                let blob = await new Promise(resolve => canvas.toBlob(resolve, mimeType, quality));

                // Lakukan reduksi kualitas jika masih di atas 900KB
                while (blob && blob.size > 900 * 1024 && quality > 0.45) {
                    quality -= 0.08;
                    blob = await new Promise(resolve => canvas.toBlob(resolve, mimeType, quality));
                }

                if (!blob) throw new Error("Gagal membuat Blob dari Canvas.");

                // Buat file baru
                const compressedName = (file.name.replace(/\.[^.]+$/, '') || 'bukti') + fileExt;
                const compressedFile = new File([blob], compressedName, { type: mimeType });

                // Ganti file di input
                const dt = new DataTransfer();
                dt.items.add(compressedFile);
                input.files = dt.files;

                // Pancing event change agar UI/Form tahu ada update file
                input.dispatchEvent(new Event('change', { bubbles: true }));

            } catch (e) {
                console.warn('Kompresi gambar gagal.', e);
            } finally {
                // 6. Lepas kunci setelah semua proses selesai atau error
                delete input.dataset.isCompressing;
            }
        });
    </script>
</div>