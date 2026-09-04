<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Borrowing;
use App\Models\BorrowingDetail;
use App\Models\User;
use App\Models\SystemNotification;
use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

new #[Layout('layouts.user')] #[Title('Riwayat Peminjaman')] class extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public string $statusFilter = 'Semua';
    public bool $isDetailModalOpen = false;
    public bool $isCancelModalOpen = false;
    public bool $isReturnModalOpen = false;
    public bool $isPrintModalOpen = false;

    public ?int $selectedBorrowingId = null;
    public array $selectedBorrowing = [];
    public array $returnDetails = [];
    public array $returnUploads = [];
    public array $returnNotes = [];

    public $file_bukti_pengembalian = null;
    public string $catatan_pengembalian = '';
    public string $returnStatus = 'Selesai';

    public array $statusTabs = ['Semua', 'Menunggu', 'Disetujui', 'Ditolak', 'Selesai'];

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    protected function baseQuery()
    {
        return Borrowing::query()
            ->with(['user', 'details.room', 'details.item'])
            ->where('user_id', auth()->id())
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('kode_transaksi', 'like', $term)
                        ->orWhere('tujuan', 'like', $term);
                });
            })
            ->when($this->statusFilter !== 'Semua', function ($q) {
                if ($this->statusFilter === 'Selesai') {
                    $q->whereIn('status', ['Selesai', 'Dikembalikan']);
                } else {
                    $q->where('status', $this->statusFilter);
                }
            });
    }

    public function with(): array
    {
        return [
            'borrowings' => $this->baseQuery()
                ->orderByDesc('created_at')
                ->orderByDesc('tanggal_mulai')
                ->orderByDesc('id')
                ->paginate(10),
        ];
    }

    protected function sendCancellationNotification(Borrowing $borrowing): void
    {
        $userName = auth()->user()?->name ?? 'User';

        $resourceNames = $borrowing->details
            ->map(fn ($detail) => $detail->room?->nama_ruangan ?? $detail->item?->nama_barang)
            ->filter()
            ->unique()
            ->values();

        $resourceName = $resourceNames->isNotEmpty()
            ? $resourceNames->implode(', ')
            : 'fasilitas';

        $admins = User::query()
            ->where(function ($query) {
                $query->where('role', 'admin');
            })
            ->get(['id']);
            
        NotificationHelper::sendToAdmins(
            title: 'Peminjaman Dibatalkan',
            message: "{$userName} membatalkan pengajuan peminjaman {$resourceName}. Transaksi {$borrowing->kode_transaksi} telah dibatalkan.",
            url: route('admin.booking')
        ); 
        // foreach ($admins as $admin) {
        //     SystemNotification::create([
        //         'user_id' => $admin->id,
        //         'title' => 'Peminjaman Dibatalkan',
        //         'message' => "{$userName} membatalkan pengajuan peminjaman {$resourceName}. Transaksi {$borrowing->kode_transaksi} telah dibatalkan.",
        //         'url' => route('admin.booking'),
        //         'is_read' => false,
        //     ]);
        // }
    }

    protected function sendReturnNotification(Borrowing $borrowing): void
    {
        if (!$borrowing->approved_by) {
            return;
        }

        $resourceNames = $borrowing->details
            ->map(fn ($detail) => $detail->room?->nama_ruangan ?? $detail->item?->nama_barang)
            ->filter()
            ->unique()
            ->values();

        $resourceName = $resourceNames->isNotEmpty()
            ? $resourceNames->implode(', ')
            : 'fasilitas';

        NotificationHelper::send(
            userId: $borrowing->approved_by,
            title: 'Pengembalian Peminjaman',
            message: "Pengembalian {$resourceName} oleh " . (auth()->user()?->name ?? 'User') . " telah diajukan untuk transaksi {$borrowing->kode_transaksi}. Silakan periksa pengembalian tersebut.",
            url: route('admin.booking')
        );

        // SystemNotification::create([
        //     'user_id' => $borrowing->approved_by,
        //     'title' => 'Pengembalian Peminjaman',
        //     'message' => "Pengembalian {$resourceName} oleh " . (auth()->user()?->name ?? 'User') . " telah diajukan untuk transaksi {$borrowing->kode_transaksi}. Silakan periksa pengembalian tersebut.",
        //     'url' => route('admin.booking'),
        //     'is_read' => false,
        // ]);
    }

    public function openDetail(int $id): void
    {
        $this->loadBorrowing($id);
        $this->isDetailModalOpen = true;
    }

    public function closeDetail(): void
    {
        $this->isDetailModalOpen = false;
        $this->selectedBorrowing = [];
        $this->selectedBorrowingId = null;
    }

    public function openCancel(int $id): void
    {
        $borrowing = $this->ownedBorrowing($id);
        abort_unless($borrowing->status === 'Menunggu', 403);
        $this->selectedBorrowingId = $id;
        $this->selectedBorrowing = [
            'id' => $borrowing->id,
            'kode_transaksi' => $borrowing->kode_transaksi,
        ];
        $this->isCancelModalOpen = true;
    }

    public function closeCancel(): void
    {
        $this->isCancelModalOpen = false;
        $this->selectedBorrowingId = null;
        $this->selectedBorrowing = [];
    }

    public function cancelBorrowing(): void
    {
        $borrowing = $this->ownedBorrowing($this->selectedBorrowingId);
        abort_unless($borrowing->status === 'Menunggu', 403);

        try {
            DB::transaction(function () use ($borrowing) {
            $userName = auth()->user()->name ?? 'User';
            $note = 'Dilakukan pembatalan oleh ' . $userName . ' pada ' . now()->format('d-m-Y H:i:s');

            $borrowing->status = 'Ditolak';

            if (Schema::hasColumn('borrowings', 'catatan')) {
                $borrowing->catatan = $note;
            } elseif (Schema::hasColumn('borrowings', 'catatan_admin')) {
                $borrowing->catatan_admin = $note;
            }

            $borrowing->save();

            foreach ($borrowing->details as $detail) {
                $detail->status = 'Ditolak';

                if (Schema::hasColumn('borrowing_details', 'catatan')) {
                    $detail->catatan = $note;
                }

                $detail->save();
            }
        });

        $this->sendCancellationNotification($borrowing);

        $code = $borrowing->kode_transaksi;

        $this->closeCancel();
        
        $this->dispatch(
            'toast',
            type: 'success',
            message: "Pengajuan {$code} berhasil dibatalkan."
        );
        } catch (\Throwable $e) {
            report($e);
            $this->addError('cancel', 'Pengajuan gagal dibatalkan. Silakan coba lagi.');
        }
    }

    public function openReturn(int $id): void
    {
        $borrowing = $this->ownedBorrowing($id);
        abort_unless($borrowing->status === 'Disetujui', 403);

        $this->selectedBorrowingId = $borrowing->id;
        $this->selectedBorrowing = $this->toSelectedArray($borrowing);

        $this->returnDetails = $borrowing->details
            ->filter(fn ($detail) => $detail->status === 'Disetujui')
            ->map(fn ($detail) => [
                'id' => $detail->id,
                'type' => $detail->room ? 'Ruangan' : 'Barang',
                'name' => $detail->room?->nama_ruangan ?? $detail->item?->nama_barang ?? '-',
                'code' => $detail->room?->kode_ruangan ?? $detail->item?->kode_barang ?? '-',
                'jumlah' => (int) $detail->jumlah,
                'status' => $detail->status,
                'catatan' => $detail->getAttribute('catatan') ?? '',
                'fasilitas' => $detail->room
                    ? $this->parseFacilities($detail->getAttribute('fasilitas'))
                    : [],
                'file' => $detail->getAttribute('bukti_pengembalian')
                    ?? $detail->getAttribute('file_bukti_pengembalian')
                    ?? null,
                'catatan_pengembalian' => $detail->getAttribute('catatan_pengembalian') ?? '',
            ])
            ->values()
            ->toArray();

        $this->returnUploads = [];
        $this->returnNotes = [];

        foreach ($this->returnDetails as $i => $detail) {
            $this->returnNotes[$i] = $detail['catatan_pengembalian'];
        }

        $this->file_bukti_pengembalian = null;
        $this->catatan_pengembalian = '';
        $this->returnStatus = 'Selesai';
        $this->resetValidation();
        $this->isReturnModalOpen = true;
    }

    public function closeReturn(): void
    {
        $this->isReturnModalOpen = false;
        $this->selectedBorrowingId = null;
        $this->selectedBorrowing = [];
        $this->returnDetails = [];
        $this->returnUploads = [];
        $this->returnNotes = [];
        $this->file_bukti_pengembalian = null;
        $this->catatan_pengembalian = '';
        $this->returnStatus = 'Selesai';
        $this->resetValidation();
    }

    public function submitReturn(): void
    {
        $borrowing = $this->ownedBorrowing($this->selectedBorrowingId);
        abort_unless($borrowing->status === 'Disetujui', 403);

        $rules = [
            'returnStatus' => ['required', Rule::in(['Dikembalikan', 'Selesai'])],
        ];

        foreach ($this->returnDetails as $index => $detail) {
            $rules["returnUploads.$index"] = [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp,heic',
                'max:1024',
            ];

            $rules["returnNotes.$index"] = [
                'nullable',
                'string',
                'max:2000',
            ];
        }

        $this->validate($rules, [
            'returnUploads.*.required' => 'Bukti pengembalian wajib diunggah.',
            'returnUploads.*.max' => 'Bukti pengembalian maksimal 1 MB.',
            'returnUploads.*.mimes' => 'Bukti harus berupa PDF atau gambar.',
        ]);

        try {
            DB::transaction(function () use ($borrowing) {
            $details = $borrowing->details->keyBy('id');
            $savedAny = false;

            foreach ($this->returnDetails as $index => $row) {
                $detail = $details->get((int) $row['id']);

                if (!$detail || $detail->status !== 'Disetujui') {
                    continue;
                }

                $upload = $this->returnUploads[$index] ?? null;

                if ($upload) {
                    $path = $upload->store('bukti-pengembalian', 'public');

                    if (Schema::hasColumn('borrowing_details', 'bukti_pengembalian')) {
                        $detail->bukti_pengembalian = $path;
                    } elseif (Schema::hasColumn('borrowing_details', 'file_bukti_pengembalian')) {
                        $detail->file_bukti_pengembalian = $path;
                    }
                }

                $note = $this->returnNotes[$index] ?? '';

                if (Schema::hasColumn('borrowing_details', 'catatan_pengembalian')) {
                    $detail->catatan_pengembalian = $note ?: null;
                }

                $detail->status = 'Dikembalikan';
                $detail->save();

                $savedAny = true;
            }

            $borrowing->refresh()->load('details');

            $approvedLeft = $borrowing->details->contains(
                fn ($d) => $d->status === 'Disetujui'
            );

            $borrowing->status = $approvedLeft
                ? 'Disetujui'
                : $this->returnStatus;

            if (Schema::hasColumn('borrowings', 'catatan_pengembalian')) {
                $notes = collect($this->returnNotes)
                    ->filter()
                    ->values()
                    ->implode(' | ');

                if ($notes !== '') {
                    $borrowing->catatan_pengembalian = $notes;
                }
            }

            if (Schema::hasColumn('borrowings', 'file_bukti_pengembalian')) {
                $firstUpload = collect($this->returnUploads)
                    ->filter()
                    ->first();

                if ($firstUpload) {
                    $borrowing->file_bukti_pengembalian =
                        $firstUpload->store(
                            'bukti-pengembalian',
                            'public'
                        );
                }
            }

            $borrowing->save();

            if (!$savedAny) {
                throw new \RuntimeException(
                    'Tidak ada item yang dapat dikembalikan.'
                );
            }
        });

        $this->sendReturnNotification($borrowing);

        $this->closeReturn();
        $this->dispatch(
            'toast',
            type: 'success',
            message: 'Pengembalian per item berhasil disimpan.'
        );
        } catch (\Throwable $e) {
            report($e);
            $this->addError('returnStatus', 'Pengembalian gagal disimpan. Silakan periksa data dan coba lagi.');
        }
    }

    public function openPrint(int $id): void
    {
        $borrowing = $this->ownedBorrowing($id);
        abort_unless($borrowing->status === 'Disetujui', 403);
        $this->selectedBorrowing = $this->toSelectedArray($borrowing);
        $this->isPrintModalOpen = true;
    }

    public function closePrint(): void
    {
        $this->isPrintModalOpen = false;
        $this->selectedBorrowing = [];
    }

    protected function ownedBorrowing(?int $id): Borrowing
    {
        return Borrowing::with(['user', 'details.room', 'details.item'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);
    }

    protected function loadBorrowing(int $id): void
    {
        $borrowing = $this->ownedBorrowing($id);
        $this->selectedBorrowingId = $borrowing->id;
        $this->selectedBorrowing = $this->toSelectedArray($borrowing);
    }

    protected function parseFacilities($value): array
    {
        if (is_array($value)) {
            return collect($value)->map(fn ($item) => trim((string) $item))->filter()->values()->toArray();
        }

        if (!$value) {
            return [];
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return collect($decoded)->map(fn ($item) => trim((string) $item))->filter()->values()->toArray();
        }

        return collect(explode(',', (string) $value))->map(fn ($item) => trim($item))->filter()->values()->toArray();
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

    protected function toSelectedArray(Borrowing $borrowing): array
    {
        return [
            'id' => $borrowing->id,
            'kode_transaksi' => $borrowing->kode_transaksi,
            'status' => $borrowing->status,
            'nama' => $borrowing->user?->name ?? '-',
            'no_hp' => $borrowing->user?->no_hp ?? $borrowing->user?->no_wa ?? '-',
            'tujuan' => $borrowing->tujuan ?? '-',
            'tanggal_mulai' => optional($borrowing->tanggal_mulai)->format('d M Y H:i'),
            'tanggal_selesai' => optional($borrowing->tanggal_selesai)->format('d M Y H:i'),
            'catatan_admin' => $borrowing->getAttribute('catatan_admin') ?? $borrowing->getAttribute('catatan') ?? '',
            'catatan_pengembalian' => $borrowing->getAttribute('catatan_pengembalian') ?? '',
            'file_lampiran' => $borrowing->getAttribute('file_lampiran') ?? null,
            'file_bukti_pengembalian' => $borrowing->getAttribute('file_bukti_pengembalian') ?? null,
            'details' => $borrowing->details->map(function (BorrowingDetail $detail) {
                return [
                    'id' => $detail->id,
                    'type' => $detail->room ? 'Ruangan' : 'Barang',
                    'name' => $detail->room?->nama_ruangan ?? $detail->item?->nama_barang ?? '-',
                    'code' => $detail->room?->kode_ruangan ?? $detail->item?->kode_barang ?? '-',
                    'jumlah' => (int) $detail->jumlah,
                    'status' => $detail->status,
                    'fasilitas' => $detail->room
                        ? $this->parseFacilities($detail->getAttribute('fasilitas'))
                        : [],
                    'catatan' => $detail->getAttribute('catatan') ?? '',
                    'file_bukti_pengembalian' => $detail->getAttribute('bukti_pengembalian')
                        ?? $detail->getAttribute('file_bukti_pengembalian')
                        ?? null,
                    'catatan_pengembalian' => $detail->getAttribute('catatan_pengembalian') ?? '',
                ];
            })->values()->toArray(),
        ];
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'Menunggu' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
            'Disetujui' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
            'Ditolak', 'Dibatalkan' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
            'Dipinjam' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
            'Dikembalikan', 'Selesai' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            default => 'bg-gray-100 text-gray-600',
        };
    }
};
?>

<div class="w-full max-w-7xl px-4 py-8 mx-auto mt-8 sm:px-6 lg:px-8 " x-data>

    <div class="mt-8 mb-6 sm:mb-8">
        <h1 class="mb-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">Riwayat Peminjaman</h1>
        <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">Pantau status persetujuan, kelola, dan unduh izin peminjaman Anda di sini.</p>
    </div>

    <div class="relative mb-5">
        <span class="absolute left-4 top-1/2 z-10 flex h-5 w-5 -translate-y-1/2 items-center justify-center text-slate-400">
            <i class="text-xs fa-solid fa-magnifying-glass"></i>
        </span>
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari kode transaksi atau tujuan..." class="w-full rounded-2xl border border-slate-200 bg-white py-3.5 pl-11 pr-11 text-sm font-medium text-slate-700 shadow-sm outline-none placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
        <span wire:loading wire:target="search" class="absolute right-4 top-1/2 -translate-y-1/2 text-brand-600 dark:text-brand-400"><i class="fa-solid fa-spinner animate-spin"></i></span>
    </div>

    <div class="mb-6 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 text-sm font-medium shadow-sm history-scrollbar-hidden dark:border-slate-700 dark:bg-slate-800">
        @foreach($statusTabs as $tab)
            <button type="button" wire:click="$set('statusFilter', @js($tab))" class="min-w-[105px] flex-1 rounded-xl px-4 py-2.5 text-center whitespace-nowrap {{ $statusFilter === $tab ? 'bg-brand-50 font-semibold text-brand-600 dark:bg-brand-900/30 dark:text-brand-400' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-700/50 dark:hover:text-slate-200' }}">
                {{ $tab }}
            </button>
        @endforeach
    </div>

    <div class="relative">
        <div wire:loading.flex wire:target="search,statusFilter,gotoPage,nextPage,previousPage"
            class="absolute inset-0 z-30 flex items-start justify-center pt-10 sm:pt-12 rounded-2xl sm:rounded-3xl bg-white/75 backdrop-blur-[2px] dark:bg-slate-900/75">
            <div
                class="flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-bold rounded-xl border border-brand-100 bg-white text-brand-600 shadow-lg dark:border-brand-900/50 dark:bg-slate-900 dark:text-brand-400">
                <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-brand-50 dark:bg-brand-900/30">
                    <i class="text-xs fa-solid fa-spinner animate-spin"></i>
                </span>
                <span>Memuat riwayat...</span>
            </div>
        </div>

        <div id="history-print-area" class="space-y-2.5 sm:space-y-3">

            @forelse($borrowings as $borrowing)
            @php($status = $borrowing->status ?? '-')

            <article wire:key="history-{{ $borrowing->id }}"
                class="overflow-hidden bg-white border rounded-2xl sm:rounded-3xl border-slate-200 dark:bg-slate-800 dark:border-slate-700 transition-all duration-200 hover:border-slate-300 hover:shadow-md dark:hover:border-slate-600">
                <div class="p-3.5 sm:p-5 lg:p-6">

                    {{-- HEADER --}}
                    <div class="flex items-start gap-3">
                        <div
                            class="flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 shrink-0">
                            <i class="text-xs sm:text-sm fa-solid fa-clipboard-list"></i>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-1 text-[8px] sm:text-[9px] font-bold rounded-md bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                                    <i class="text-[7px] fa-solid fa-hashtag"></i>
                                    {{ $borrowing->kode_transaksi }}
                                </span>

                                <span
                                    class="inline-flex items-center px-2 py-1 text-[8px] sm:text-[9px] font-bold rounded-full {{ $this->statusClass($status) }}">
                                    {{ $status }}
                                </span>
                            </div>

                            <h3
                                class="mt-1.5 text-[12px] sm:text-[15px] font-extrabold leading-snug text-slate-900 dark:text-white break-words">
                                {{ $borrowing->tujuan ?: 'Peminjaman fasilitas' }}
                            </h3>
                        </div>
                    </div>

                    {{-- DATE SUMMARY --}}
                    <div class="grid grid-cols-1 gap-2.5 mt-4 sm:grid-cols-2">

                        {{-- TANGGAL PENGAJUAN --}}
                        <div
                            class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl bg-brand-50/70 border border-brand-100 dark:bg-brand-500/5 dark:border-brand-500/20">
                            <div
                                class="flex items-center justify-center w-8 h-8 rounded-lg bg-white text-brand-600 dark:bg-slate-800 dark:text-brand-400 shrink-0 shadow-sm">
                                <i class="text-[10px] fa-solid fa-paper-plane"></i>
                            </div>

                            <div class="min-w-0">
                                <div class="text-[8px] sm:text-[9px] font-bold uppercase tracking-wide text-brand-500">
                                    Tanggal Pengajuan
                                </div>

                                <div class="mt-0.5 text-[10px] sm:text-[11px] font-bold text-slate-700 dark:text-slate-200">
                                    {{ optional($borrowing->created_at)->format('d M Y, H:i') ?? '-' }}
                                </div>
                            </div>
                        </div>

                        {{-- PERIODE --}}
                        <div
                            class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-100 dark:bg-slate-900/50 dark:border-slate-700">
                            <div
                                class="flex items-center justify-center w-8 h-8 rounded-lg bg-white text-slate-500 dark:bg-slate-800 dark:text-slate-400 shrink-0 shadow-sm">
                                <i class="text-[10px] fa-regular fa-calendar"></i>
                            </div>

                            <div class="min-w-0">
                                <div class="text-[8px] sm:text-[9px] font-bold uppercase tracking-wide text-slate-400">
                                    Periode Peminjaman
                                </div>

                                <div class="mt-0.5 text-[10px] sm:text-[11px] font-bold text-slate-700 dark:text-slate-200">
                                    {{ optional($borrowing->tanggal_mulai)->format('d M Y, H:i') }}
                                </div>

                                <div class="text-[9px] sm:text-[10px] text-slate-400">
                                    s/d {{ optional($borrowing->tanggal_selesai)->format('d M Y, H:i') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- META --}}
                    <div class="grid grid-cols-2 gap-2 mt-2.5 sm:grid-cols-3">

                        {{-- PEMINJAM --}}
                        <div
                            class="flex items-center gap-2 px-3 py-2.5 min-w-0 rounded-xl bg-slate-50 dark:bg-slate-900/50">
                            <div
                                class="flex items-center justify-center w-7 h-7 rounded-lg bg-white text-slate-500 dark:bg-slate-800 dark:text-slate-400 shrink-0">
                                <i class="text-[9px] fa-solid fa-user"></i>
                            </div>

                            <div class="min-w-0">
                                <div class="text-[7px] sm:text-[8px] font-bold uppercase tracking-wide text-slate-400">
                                    Peminjam
                                </div>

                                <div
                                    class="mt-0.5 text-[9px] sm:text-[10px] font-bold truncate text-slate-700 dark:text-slate-200">
                                    {{ $borrowing->user?->name ?? '-' }}
                                </div>
                            </div>
                        </div>

                        {{-- ITEM --}}
                        <div
                            class="flex items-center gap-2 px-3 py-2.5 min-w-0 rounded-xl bg-slate-50 dark:bg-slate-900/50">
                            <div
                                class="flex items-center justify-center w-7 h-7 rounded-lg bg-white text-slate-500 dark:bg-slate-800 dark:text-slate-400 shrink-0">
                                <i class="text-[9px] fa-solid fa-list-check"></i>
                            </div>

                            <div class="min-w-0">
                                <div class="text-[7px] sm:text-[8px] font-bold uppercase tracking-wide text-slate-400">
                                    Item
                                </div>

                                <div class="mt-0.5 text-[9px] sm:text-[10px] font-bold text-slate-700 dark:text-slate-200">
                                    {{ $borrowing->details->count() }} item
                                </div>
                            </div>
                        </div>

                        {{-- LAMPIRAN --}}
                        <div
                            class="flex items-center gap-2 px-3 py-2.5 col-span-2 min-w-0 rounded-xl bg-slate-50 dark:bg-slate-900/50 sm:col-span-1">
                            <div
                                class="flex items-center justify-center w-7 h-7 rounded-lg bg-white text-slate-500 dark:bg-slate-800 dark:text-slate-400 shrink-0">
                                <i class="text-[9px] fa-solid fa-paperclip"></i>
                            </div>

                            <div class="min-w-0">
                                <div class="text-[7px] sm:text-[8px] font-bold uppercase tracking-wide text-slate-400">
                                    Lampiran
                                </div>

                                <div
                                    class="mt-0.5 text-[9px] sm:text-[10px] font-bold truncate text-slate-700 dark:text-slate-200">
                                    {{ $borrowing->file_lampiran ? 'Tersedia' : 'Tidak ada' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ACTION --}}
                    <div class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-700">

                        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end">

                            {{-- DETAIL --}}
                            <button type="button" wire:click="openDetail({{ $borrowing->id }})"
                                class="inline-flex items-center justify-center gap-1.5 h-9 px-3 rounded-lg text-[10px] sm:text-[11px] font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600 transition-colors">
                                <i class="text-[9px] fa-solid fa-eye"></i>
                                Detail
                            </button>

                            {{-- LAMPIRAN --}}
                            @if($borrowing->file_lampiran)
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($borrowing->file_lampiran) }}"
                                target="_blank" rel="noopener"
                                class="no-print inline-flex items-center justify-center gap-1.5 h-9 px-3 rounded-lg border border-slate-200 bg-white text-[10px] sm:text-[11px] font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition-colors">
                                <i class="text-[9px] fa-solid fa-paperclip"></i>
                                Lampiran
                            </a>
                            @endif

                            {{-- BATAL --}}
                            @if($status === 'Menunggu')
                            <button type="button" wire:click="openCancel({{ $borrowing->id }})"
                                class="no-print col-span-2 sm:col-span-1 inline-flex items-center justify-center gap-1.5 h-9 px-3 rounded-lg text-[10px] sm:text-[11px] font-bold bg-rose-50 text-rose-600 hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 transition-colors">
                                <i class="text-[9px] fa-solid fa-ban"></i>
                                Batalkan
                            </button>
                            @endif

                            {{-- DISETUJUI --}}
                            @if($status === 'Disetujui')
                            <button type="button" wire:click="openReturn({{ $borrowing->id }})"
                                class="no-print col-span-2 sm:col-span-1 inline-flex items-center justify-center gap-1.5 h-9 px-3 rounded-lg text-[10px] sm:text-[11px] font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors">
                                <i class="text-[9px] fa-solid fa-box-open"></i>
                                Pengembalian
                            </button>

                            <button type="button" wire:click="openPrint({{ $borrowing->id }})"
                                class="no-print col-span-2 sm:col-span-1 inline-flex items-center justify-center gap-1.5 h-9 px-3 rounded-lg text-[10px] sm:text-[11px] font-bold bg-brand-600 text-white hover:bg-brand-700 transition-colors">
                                <i class="text-[9px] fa-solid fa-print"></i>
                                Cetak
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </article>

            @empty
            <div
                class="px-5 py-12 text-center bg-white border border-dashed rounded-2xl sm:rounded-3xl border-slate-300 dark:border-slate-700 dark:bg-slate-800">
                <div
                    class="flex items-center justify-center w-12 h-12 mx-auto rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-900">
                    <i class="text-lg fa-solid fa-clock-rotate-left"></i>
                </div>

                <h3 class="mt-4 text-sm font-extrabold text-slate-700 dark:text-slate-200">
                    Belum ada riwayat peminjaman
                </h3>

                <p class="max-w-xs mx-auto mt-1 text-[10px] sm:text-xs leading-relaxed text-slate-400">
                    Data peminjaman Anda akan muncul di halaman ini.
                </p>
            </div>
            @endforelse

        </div>
    </div>

    <div class="mt-6">{{ $borrowings->links() }}</div>

    {{-- MODAL DETAIL --}}
    <template x-teleport="body">
        <div x-data="{ open: @entangle('isDetailModalOpen') }" x-show="open" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-2 sm:p-4">
            <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm"></div>
            <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-[0.98] translate-y-2" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100 translate-y-0" x-transition:leave-end="opacity-0 scale-[0.98] translate-y-2" class="relative flex w-full max-w-5xl max-h-[96vh] flex-col overflow-hidden rounded-2xl sm:rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200/80 bg-white/95 px-4 py-3.5 backdrop-blur sm:px-6 sm:py-4 dark:border-slate-800 dark:bg-slate-900/95">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                            <i class="text-sm fa-solid fa-file-lines"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-sm font-extrabold text-slate-900 sm:text-base dark:text-white">Detail Peminjaman</h2>
                            </div>
                            <p class="mt-0.5 truncate text-[9px] font-semibold text-brand-600 sm:text-[10px] dark:text-brand-400">{{ $selectedBorrowing['kode_transaksi'] ?? '-' }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeDetail" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition hover:bg-rose-50 hover:text-rose-500 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-rose-500/10 dark:hover:text-rose-400" aria-label="Tutup modal">
                        <i class="text-xs fa-solid fa-xmark"></i>
                    </button>
                </div>
            <div class="min-h-0 flex-1 overflow-y-auto hide-scrollbar">
                <div class="space-y-5 p-4 sm:p-6">
                    <section>
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-xs font-extrabold text-slate-800 sm:text-sm dark:text-white">Ringkasan Peminjaman</h3>
                                <p class="mt-0.5 text-[9px] sm:text-[10px] text-slate-400">Informasi utama pengajuan peminjaman.</p>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[8px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                <i class="text-[8px] fa-solid fa-circle-info"></i>
                                Detail
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-800/60">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                                        <i class="text-[10px] fa-solid fa-chart-simple"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-[8px] font-bold uppercase tracking-wide text-slate-400">Status</div>
                                        <div class="mt-1">
                                            <span class="inline-flex max-w-full rounded-full px-2 py-1 text-[9px] font-bold {{ $this->statusClass($selectedBorrowing['status'] ?? '') }}">{{ $selectedBorrowing['status'] ?? '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-800/60">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300">
                                        <i class="text-[10px] fa-solid fa-user"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-[8px] font-bold uppercase tracking-wide text-slate-400">Peminjam</div>
                                        <div class="mt-1 truncate text-[11px] font-bold text-slate-800 dark:text-white">{{ $selectedBorrowing['nama'] ?? '-' }}</div>
                                        <div class="mt-0.5 truncate text-[9px] text-slate-400">{{ $selectedBorrowing['no_hp'] ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-800/60">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300">
                                        <i class="text-[10px] fa-regular fa-calendar-plus"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-[8px] font-bold uppercase tracking-wide text-slate-400">Mulai</div>
                                        <div class="mt-1 break-words text-[11px] font-bold text-slate-800 dark:text-white">{{ $selectedBorrowing['tanggal_mulai'] ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-800/60">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300">
                                        <i class="text-[10px] fa-regular fa-calendar-check"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-[8px] font-bold uppercase tracking-wide text-slate-400">Selesai</div>
                                        <div class="mt-1 break-words text-[11px] font-bold text-slate-800 dark:text-white">{{ $selectedBorrowing['tanggal_selesai'] ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="mb-2.5 flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                                <i class="text-[10px] fa-solid fa-bullseye"></i>
                            </span>
                            <div>
                                <h3 class="text-xs font-extrabold text-slate-800 sm:text-sm dark:text-white">Tujuan / Keperluan</h3>
                                <p class="text-[9px] text-slate-400">Tujuan penggunaan fasilitas.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-2 lg:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-800/60">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-salte-100 text-salte-600 dark:bg-salte-500/10 dark:text-salte-400">
                                        <i class="text-xs fa-solid fa-bullseye"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[10px] font-extrabold text-salte-800 sm:text-xs dark:text-salte-200">Tujuan</div>
                                        <div class="mt-1 text-[10px] leading-relaxed text-salte-800 sm:text-xs dark:text-salte-200">
                                            {{ $selectedBorrowing['tujuan'] ?? 'Tidak ada catatan admin.' }}
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-800/60">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-salte-100 text-salte-600 dark:bg-salte-500/10 dark:text-salte-400">
                                        <i class="text-xs fa-solid fa-note-sticky"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[10px] font-extrabold text-salte-800 sm:text-xs dark:text-salte-200">Catatan Peminjaman</div>
                                        <div class="mt-1 text-[10px] leading-relaxed text-salte-800 sm:text-xs dark:text-salte-200">
                                            {{ $selectedBorrowing['catatan'] ?? 'Tidak ada catatan admin.' }}
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="rounded-2xl border border-amber-200/70 bg-amber-50/70 p-3.5 dark:border-amber-900/40 dark:bg-amber-900/15">
                            <div class="flex items-start gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                                    <i class="text-xs fa-solid fa-note-sticky"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-[10px] font-extrabold text-amber-800 sm:text-xs dark:text-amber-200">Catatan Admin</div>
                                    <div class="mt-1 text-[10px] leading-relaxed text-amber-800 sm:text-xs dark:text-amber-200">
                                        {{ $selectedBorrowing['catatan_admin'] ?? 'Tidak ada catatan admin.' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="mb-3 flex items-end justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                                        <i class="text-[10px] fa-solid fa-boxes-stacked"></i>
                                    </span>
                                    <div>
                                        <h3 class="text-xs font-extrabold text-slate-800 sm:text-sm dark:text-white">Fasilitas yang Dipinjam</h3>
                                        <p class="text-[9px] text-slate-400">Rincian fasilitas dalam pengajuan.</p>
                                    </div>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[9px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                {{ count($selectedBorrowing['details'] ?? []) }} item
                            </span>
                        </div>

                        <div class="hidden lg:block overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800">
                            <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-slate-800 dark:bg-slate-800/70">
                                <div class="flex items-center gap-2 text-[9px] font-semibold text-slate-400">
                                    <i class="fa-solid fa-arrows-left-right"></i>
                                    Geser tabel untuk melihat informasi lainnya
                                </div>
                                <span class="text-[9px] text-slate-400">{{ count($selectedBorrowing['details'] ?? []) }} fasilitas</span>
                            </div>

                            <div class="overflow-x-auto hide-scrollbar">
                                <table class="min-w-[1050px] w-full text-left text-[10px]">
                                    <thead class="bg-white dark:bg-slate-900">
                                        <tr class="border-b border-slate-100 dark:border-slate-800">
                                            <th class="px-4 py-3 font-extrabold uppercase tracking-wide text-slate-400">Tipe</th>
                                            <th class="px-4 py-3 font-extrabold uppercase tracking-wide text-slate-400">Kode</th>
                                            <th class="px-4 py-3 font-extrabold uppercase tracking-wide text-slate-400">Nama</th>
                                            <th class="px-4 py-3 font-extrabold uppercase tracking-wide text-slate-400">Fasilitas</th>
                                            <th class="px-4 py-3 text-center font-extrabold uppercase tracking-wide text-slate-400">Qty</th>
                                            <th class="px-4 py-3 text-center font-extrabold uppercase tracking-wide text-slate-400">Status</th>
                                            <th class="px-4 py-3 font-extrabold uppercase tracking-wide text-slate-400">Catatan</th>
                                            <th class="px-4 py-3 font-extrabold uppercase tracking-wide text-slate-400">Pengembalian</th>
                                            <th class="px-4 py-3 text-center font-extrabold uppercase tracking-wide text-slate-400">Bukti</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        @forelse(($selectedBorrowing['details'] ?? []) as $detail)
                                            <tr class="align-top hover:bg-slate-50/70 dark:hover:bg-slate-800/50">
                                                <td class="px-4 py-3.5">
                                                    <span class="inline-flex rounded-md bg-slate-100 px-2 py-1 text-[9px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $detail['type'] }}</span>
                                                </td>
                                                <td class="px-4 py-3.5 font-bold text-brand-600 dark:text-brand-400">#{{ $detail['code'] }}</td>
                                                <td class="px-4 py-3.5">
                                                    <div class="max-w-[200px] break-words font-bold leading-relaxed text-slate-800 dark:text-white">{{ $detail['name'] }}</div>
                                                </td>
                                                <td class="px-4 py-3.5">
                                                    @if($detail['type'] === 'Ruangan')
                                                        <div class="flex max-w-[230px] flex-wrap gap-1">
                                                            @forelse($detail['fasilitas'] ?? [] as $facility)
                                                                <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-1 text-[8px] font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                                                    <i class="fa-solid {{ $this->facilityIcon($facility) }}"></i>
                                                                    {{ $facility }}
                                                                </span>
                                                            @empty
                                                                <span class="text-[9px] text-slate-400">Tidak ada</span>
                                                            @endforelse
                                                        </div>
                                                    @else
                                                        <span class="text-[9px] text-slate-400">-</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3.5 text-center font-extrabold text-slate-800 dark:text-white">{{ $detail['jumlah'] }}</td>
                                                <td class="px-4 py-3.5 text-center">
                                                    <span class="inline-flex whitespace-nowrap rounded-full px-2 py-1 text-[8px] font-bold {{ $this->statusClass($detail['status']) }}">{{ $detail['status'] }}</span>
                                                </td>
                                                <td class="px-4 py-3.5 text-slate-600 dark:text-slate-300">
                                                    <div class="max-w-[210px] break-words leading-relaxed">{{ $detail['catatan'] ?: '-' }}</div>
                                                </td>
                                                <td class="px-4 py-3.5 text-slate-600 dark:text-slate-300">
                                                    <div class="max-w-[220px] break-words leading-relaxed">{{ $detail['catatan_pengembalian'] ?: '-' }}</div>
                                                </td>
                                                <td class="px-4 py-3.5 text-center">
                                                    @if(!empty($detail['file_bukti_pengembalian']))
                                                        <a href="{{ \Illuminate\Support\Facades\Storage::url($detail['file_bukti_pengembalian']) }}" target="_blank" rel="noopener" title="Lihat bukti pengembalian" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 transition hover:bg-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-300">
                                                            <i class="text-[9px] fa-solid fa-arrow-up-right-from-square"></i>
                                                        </a>
                                                    @else
                                                        <span class="text-slate-400">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="p-10 text-center">
                                                    <div class="flex flex-col items-center">
                                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-300 dark:bg-slate-800 dark:text-slate-600">
                                                            <i class="text-sm fa-solid fa-inbox"></i>
                                                        </div>
                                                        <span class="mt-2 text-[10px] font-medium text-slate-400">Tidak ada rincian fasilitas.</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="space-y-2 lg:hidden">
                            @forelse(($selectedBorrowing['details'] ?? []) as $detail)
                                <div x-data="{ detailOpen: false }" class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-800/60">
                                    <button type="button" @click="detailOpen = !detailOpen" class="flex w-full items-center gap-3 px-3.5 py-3 text-left">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                                            <i class="text-xs fa-solid {{ $detail['type'] === 'Ruangan' ? 'fa-door-open' : 'fa-box' }}"></i>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[8px] font-bold uppercase text-slate-400">{{ $detail['type'] }}</span>
                                                <span class="text-[9px] font-bold text-brand-600 dark:text-brand-400">#{{ $detail['code'] }}</span>
                                            </div>
                                            <div class="mt-0.5 break-words text-[11px] font-extrabold text-slate-800 dark:text-white">{{ $detail['name'] }}</div>
                                        </div>
                                        <span class="shrink-0 rounded-full px-2 py-1 text-[8px] font-bold {{ $this->statusClass($detail['status']) }}">{{ $detail['status'] }}</span>
                                        <i class="text-[9px] fa-solid fa-chevron-down text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': detailOpen }"></i>
                                    </button>

                                    <div x-show="detailOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1" class="border-t border-slate-100 dark:border-slate-800">
                                        <div class="space-y-3 p-3.5">
                                            <div class="grid grid-cols-2 gap-2">
                                                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/50">
                                                    <div class="text-[8px] font-bold uppercase tracking-wide text-slate-400">Jumlah</div>
                                                    <div class="mt-1 text-sm font-extrabold text-slate-800 dark:text-white">{{ $detail['jumlah'] }}</div>
                                                </div>
                                                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/50">
                                                    <div class="text-[8px] font-bold uppercase tracking-wide text-slate-400">Bukti</div>
                                                    <div class="mt-1">
                                                        @if(!empty($detail['file_bukti_pengembalian']))
                                                            <a href="{{ \Illuminate\Support\Facades\Storage::url($detail['file_bukti_pengembalian']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1.5 text-[9px] font-bold text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300">
                                                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                                                Lihat
                                                            </a>
                                                        @else
                                                            <span class="text-[10px] text-slate-400">Tidak ada</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            @if($detail['type'] === 'Ruangan')
                                                <div class="rounded-xl bg-brand-50/70 p-3 dark:bg-brand-500/5">
                                                    <div class="mb-2 text-[8px] font-bold uppercase tracking-wide text-brand-600 dark:text-brand-300">Fasilitas yang digunakan</div>
                                                    <div class="flex flex-wrap gap-1.5">
                                                        @forelse($detail['fasilitas'] ?? [] as $facility)
                                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1.5 text-[9px] font-semibold text-brand-700 dark:bg-slate-900 dark:text-brand-300">
                                                                <i class="text-[8px] fa-solid {{ $this->facilityIcon($facility) }}"></i>
                                                                {{ $facility }}
                                                            </span>
                                                        @empty
                                                            <span class="text-[9px] text-slate-400">Tidak ada fasilitas.</span>
                                                        @endforelse
                                                    </div>
                                                </div>
                                            @endif

                                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/50">
                                                <div class="text-[8px] font-bold uppercase tracking-wide text-slate-400">Catatan</div>
                                                <div class="mt-1 text-[10px] leading-relaxed text-slate-600 dark:text-slate-300">{{ $detail['catatan'] ?: 'Tidak ada catatan.' }}</div>
                                            </div>

                                            <div class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-900/15">
                                                <div class="text-[8px] font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">Catatan Pengembalian</div>
                                                <div class="mt-1 text-[10px] leading-relaxed text-emerald-800 dark:text-emerald-200">{{ $detail['catatan_pengembalian'] ?: 'Belum ada catatan pengembalian.' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center dark:border-slate-700">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-300 dark:bg-slate-800 dark:text-slate-600">
                                        <i class="text-lg fa-solid fa-inbox"></i>
                                    </div>
                                    <div class="mt-2 text-[10px] text-slate-400">Tidak ada rincian fasilitas.</div>
                                </div>
                            @endforelse
                        </div>
                    </section>

                    @if(!empty($selectedBorrowing['file_lampiran']) || !empty($selectedBorrowing['file_bukti_pengembalian']))
                        <section>
                            <div class="mb-3 flex items-center gap-2">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                    <i class="text-[10px] fa-solid fa-paperclip"></i>
                                </span>
                                <div>
                                    <h3 class="text-xs font-extrabold text-slate-800 sm:text-sm dark:text-white">Dokumen Pendukung</h3>
                                    <p class="text-[9px] text-slate-400">Dokumen yang terkait dengan peminjaman.</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @if(!empty($selectedBorrowing['file_lampiran']))
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($selectedBorrowing['file_lampiran']) }}" target="_blank" rel="noopener" class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3.5 py-3 transition hover:border-brand-300 hover:bg-brand-50/40 dark:border-slate-800 dark:bg-slate-800/60 dark:hover:border-brand-500/40">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                                            <i class="text-xs fa-solid fa-paperclip"></i>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[10px] font-bold text-slate-700 dark:text-slate-200">Lampiran SP</span>
                                            <span class="block mt-0.5 text-[9px] text-slate-400">Buka dokumen</span>
                                        </span>
                                        <i class="text-[9px] fa-solid fa-arrow-up-right-from-square text-slate-400 group-hover:text-brand-500"></i>
                                    </a>
                                @endif

                                @if(!empty($selectedBorrowing['file_bukti_pengembalian']))
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($selectedBorrowing['file_bukti_pengembalian']) }}" target="_blank" rel="noopener" class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3.5 py-3 transition hover:border-emerald-300 hover:bg-emerald-50/40 dark:border-slate-800 dark:bg-slate-800/60 dark:hover:border-emerald-500/40">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                                            <i class="text-xs fa-solid fa-file-arrow-up"></i>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[10px] font-bold text-slate-700 dark:text-slate-200">Bukti Pengembalian</span>
                                            <span class="block mt-0.5 text-[9px] text-slate-400">Buka dokumen</span>
                                        </span>
                                        <i class="text-[9px] fa-solid fa-arrow-up-right-from-square text-slate-400 group-hover:text-emerald-500"></i>
                                    </a>
                                @endif
                            </div>
                        </section>
                    @endif
                </div>
            </div>

            <div class="shrink-0 border-t border-slate-200/80 bg-white/95 px-4 py-3.5 backdrop-blur sm:px-6 dark:border-slate-800 dark:bg-slate-900/95">
                <div class="flex justify-end">
                    <button type="button" wire:click="closeDetail" class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-slate-100 px-5 text-xs font-bold text-slate-700 transition hover:bg-slate-200 sm:w-auto dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
    </template>

    {{-- MODAL PENGEMBALIAN --}}
    <template x-teleport="body">
        <div x-data="{ open: @entangle('isReturnModalOpen') }" x-show="open" x-cloak class="fixed inset-0 z-[140] flex items-center justify-center p-2 sm:p-4">
            <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm"></div>
            <div x-show="open" x-transition class="relative flex w-full max-w-4xl max-h-[96vh] flex-col overflow-hidden rounded-2xl sm:rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 bg-white/95 px-4 py-4 backdrop-blur sm:px-6 dark:border-slate-800 dark:bg-slate-900/95">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                            <i class="text-sm fa-solid fa-arrow-rotate-left"></i>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm font-extrabold text-slate-900 sm:text-lg dark:text-white">Pengembalian Peminjaman</h3>
                            <p class="mt-0.5 truncate text-[9px] font-semibold text-brand-600 sm:text-[10px] dark:text-brand-400">{{ $selectedBorrowing['kode_transaksi'] ?? '-' }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeReturn" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition hover:bg-rose-50 hover:text-rose-500 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-rose-500/10">
                        <i class="text-xs fa-solid fa-xmark"></i>
                    </button>
                </div>
            <form wire:submit="submitReturn" class="min-h-0 flex-1 overflow-y-auto hide-scrollbar">
                <div class="space-y-5 p-4 sm:p-6">
                    <div class="rounded-2xl border border-brand-100 bg-brand-50/60 p-4 dark:border-brand-500/20 dark:bg-brand-500/5">
                        <div class="flex items-start gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600 shadow-sm dark:bg-slate-800 dark:text-brand-400">
                                <i class="text-xs fa-solid fa-circle-info"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-extrabold text-brand-800 sm:text-xs dark:text-brand-300">Proses Pengembalian</p>
                                <p class="mt-0.5 text-[9px] leading-relaxed text-brand-700/80 sm:text-[10px] dark:text-brand-300/50">Pastikan setiap fasilitas dikembalikan sesuai kondisi dan lengkapi bukti serta catatan bila diperlukan.</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        
                        <div class="space-y-5 mb-3">
                            <section>
                                    <div class="mb-2.5 flex items-center gap-2">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                                            <i class="text-[10px] fa-solid fa-bullseye"></i>
                                        </span>
                                        <div>
                                            <h3 class="text-xs font-extrabold text-slate-800 sm:text-sm dark:text-white">Tujuan / Keperluan</h3>
                                            <p class="text-[9px] text-slate-400">Tujuan penggunaan fasilitas.</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 gap-2 lg:grid-cols-2">
                                        <div class="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-800/60">
                                            <div class="flex items-start gap-3">
                                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-salte-100 text-salte-600 dark:bg-salte-500/10 dark:text-salte-400">
                                                    <i class="text-xs fa-solid fa-bullseye"></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="text-[10px] font-extrabold text-salte-800 sm:text-xs dark:text-salte-200">Tujuan</div>
                                                    <div class="mt-1 text-[10px] leading-relaxed text-salte-800 sm:text-xs dark:text-salte-200">
                                                        {{ $selectedBorrowing['tujuan'] ?? 'Tidak ada catatan admin.' }}
                                                    </div>
                                                </div>
                                            </div>
                                            
                                        </div>
                                        <div class="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-800/60">
                                            <div class="flex items-start gap-3">
                                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-salte-100 text-salte-600 dark:bg-salte-500/10 dark:text-salte-400">
                                                    <i class="text-xs fa-solid fa-note-sticky"></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="text-[10px] font-extrabold text-salte-800 sm:text-xs dark:text-salte-200">Catatan Peminjaman</div>
                                                    <div class="mt-1 text-[10px] leading-relaxed text-salte-800 sm:text-xs dark:text-salte-200">
                                                        {{ $selectedBorrowing['catatan'] ?? 'Tidak ada catatan admin.' }}
                                                    </div>
                                                </div>
                                            </div>
                                            
                                        </div>
                                    </div>
                            </section>
                            <section>
                                    <div class="rounded-2xl border border-amber-200/70 bg-amber-50/70 p-3.5 dark:border-amber-900/40 dark:bg-amber-900/15">
                                        <div class="flex items-start gap-3">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                                                <i class="text-xs fa-solid fa-note-sticky"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-[10px] font-extrabold text-amber-800 sm:text-xs dark:text-amber-200">Catatan Admin</div>
                                                <div class="mt-1 text-[10px] leading-relaxed text-amber-800 sm:text-xs dark:text-amber-200">
                                                    {{ $selectedBorrowing['catatan_admin'] ?? 'Tidak ada catatan admin.' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                            </section>
                        </div>
                        <div class="mb-3 flex items-end justify-between gap-3">
                            <div>
                                <h4 class="text-xs font-extrabold text-slate-800 sm:text-sm dark:text-white">Fasilitas yang Dikembalikan</h4>
                                <p class="mt-0.5 text-[9px] sm:text-[10px] text-slate-400">Lengkapi data pengembalian untuk setiap item.</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[9px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ count($returnDetails) }} item</span>
                        </div>
                        <div class="space-y-3 md:hidden">
                            @forelse($returnDetails as $index => $detail)
                                <div wire:key="return-mobile-{{ $detail['id'] }}" class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-800/60">
                                    <div class="p-3.5">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex min-w-0 flex-1 items-start gap-2.5">
                                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                                                    <i class="text-xs fa-solid {{ $detail['type'] === 'Ruangan' ? 'fa-door-open' : 'fa-box' }}"></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-[8px] font-bold uppercase text-slate-500 dark:bg-slate-700 dark:text-slate-400">{{ $detail['type'] }}</span>
                                                        <span class="text-[9px] font-bold text-brand-600 dark:text-brand-400">#{{ $detail['code'] }}</span>
                                                    </div>
                                                    <div class="mt-1 break-words text-xs font-extrabold text-slate-800 dark:text-white">{{ $detail['name'] }}</div>
                                                </div>
                                            </div>
                                            <span class="shrink-0 rounded-full px-2 py-1 text-[8px] font-bold {{ $this->statusClass($detail['status']) }}">{{ $detail['status'] }}</span>
                                        </div>

                                        @if($detail['type'] === 'Ruangan')
                                            <div class="mt-3 rounded-xl bg-emerald-50/70 p-3 dark:bg-emerald-900/10">
                                                <div class="mb-2 text-[8px] font-extrabold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">Fasilitas yang digunakan</div>
                                                <div class="flex flex-wrap gap-1.5">
                                                    @forelse($detail['fasilitas'] ?? [] as $facility)
                                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1.5 text-[9px] font-semibold text-emerald-700 dark:bg-slate-900 dark:text-emerald-300">
                                                            <i class="text-[8px] fa-solid {{ $this->facilityIcon($facility) }}"></i>
                                                            {{ $facility }}
                                                        </span>
                                                    @empty
                                                        <span class="text-[9px] text-slate-400">Tidak ada fasilitas yang digunakan.</span>
                                                    @endforelse
                                                </div>
                                            </div>
                                        @endif

                                        <div class="mt-3 grid grid-cols-2 gap-2">
                                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/50">
                                                <div class="text-[8px] font-bold uppercase tracking-wide text-slate-400">Jumlah</div>
                                                <div class="mt-1 text-sm font-extrabold text-slate-800 dark:text-white">{{ $detail['jumlah'] }}</div>
                                            </div>

                                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/50">
                                                <div class="text-[8px] font-bold uppercase tracking-wide text-slate-400">Status</div>
                                                <div class="mt-1">
                                                    <span class="inline-flex rounded-full px-2 py-1 text-[8px] font-bold {{ $this->statusClass($detail['status']) }}">{{ $detail['status'] }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-3">
                                            <label class="mb-1.5 block text-[9px] font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">Bukti Pengembalian</label>
                                            <input type="file" wire:model="returnUploads.{{ $index }}" data-compress-return accept="application/pdf,image/*" capture="environment" class="block w-full text-[10px] text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-[10px] file:font-bold file:text-brand-700 dark:file:bg-brand-900/30 dark:file:text-brand-300">
                                            <p class="mt-1.5 flex items-start gap-1.5 text-[9px] leading-relaxed text-slate-400">
                                                <i class="mt-0.5 fa-solid fa-circle-info text-brand-500"></i>
                                                Foto dokumentasi kondisi barang/ruangan saat dikembalikan.
                                            </p>

                                            <div wire:loading wire:target="returnUploads.{{ $index }}" class="mt-1.5 text-[9px] font-semibold text-brand-600 dark:text-brand-400">
                                                <i class="mr-1 fa-solid fa-spinner animate-spin"></i>
                                                Memproses file...
                                            </div>

                                            @if(!empty($returnUploads[$index]))
                                                <div class="mt-1.5 flex items-start gap-1.5 break-all text-[9px] font-semibold text-emerald-600 dark:text-emerald-300">
                                                    <i class="mt-0.5 fa-solid fa-circle-check"></i>
                                                    <span>{{ $returnUploads[$index]->getClientOriginalName() }}</span>
                                                </div>
                                            @endif

                                            @error('returnUploads.'.$index)
                                                <span class="mt-1 block text-[9px] font-semibold text-rose-500">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-2">
                                            <div class="">
                                                <label class="mb-1.5 block text-[9px] font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">Catatan Admin</label>
                                                <input type="text"  placeholder="Tuliskan kondisi atau catatan..." class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-[10px] outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-900/50 dark:text-white" value="{{ $detail['catatan'] }}" disabled>
                                                
                                            </div>
                                            <div class="">
                                                <label class="mb-1.5 block text-[9px] font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">Catatan Pengembalian</label>
                                                <input type="text" wire:model="returnNotes.{{ $index }}" placeholder="Tuliskan kondisi atau catatan..." class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-[10px] outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-900/50 dark:text-white">
                                                @error('returnNotes.'.$index)
                                                    <span class="mt-1 block text-[9px] font-semibold text-rose-500">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center dark:border-slate-700">
                                    <div class="flex h-10 w-10 items-center justify-center mx-auto rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                                        <i class="text-sm fa-solid fa-inbox"></i>
                                    </div>
                                    <p class="mt-2 text-[10px] font-medium text-slate-400">Tidak ada item yang berstatus Disetujui.</p>
                                </div>
                            @endforelse
                        </div>

                        
                        <div class="hidden overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 md:block">
                            <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-slate-800 dark:bg-slate-800/70">
                                <div class="flex items-center gap-2 text-[9px] font-semibold text-slate-400">
                                    <i class="fa-solid fa-arrows-left-right"></i>
                                    Geser untuk melihat seluruh kolom
                                </div>
                                <span class="text-[9px] text-slate-400">{{ count($returnDetails) }} item</span>
                            </div>

                            <div class="overflow-x-auto hide-scrollbar">
                                <table class="w-full min-w-[950px] text-left text-[10px]">
                                    <thead class="bg-white dark:bg-slate-900">
                                        <tr class="border-b border-slate-100 dark:border-slate-800">
                                            <th class="px-4 py-3 font-extrabold uppercase tracking-wide text-slate-400">Tipe</th>
                                            <th class="px-4 py-3 font-extrabold uppercase tracking-wide text-slate-400">Kode</th>
                                            <th class="px-4 py-3 font-extrabold uppercase tracking-wide text-slate-400">Nama</th>
                                            <th class="px-4 py-3 font-extrabold uppercase tracking-wide text-slate-400">Fasilitas</th>
                                            <th class="px-4 py-3 text-center font-extrabold uppercase tracking-wide text-slate-400">Jumlah</th>
                                            <th class="min-w-[260px] px-4 py-3 font-extrabold uppercase tracking-wide text-slate-400">Catatan(admin)</th>
                                            <th class="min-w-[260px] px-4 py-3 font-extrabold uppercase tracking-wide text-slate-400">Catatan Pengembalian</th>
                                            <th class="min-w-[290px] px-4 py-3 font-extrabold uppercase tracking-wide text-slate-400">Bukti Pengembalian</th>
                                        </tr>
                                    </thead>

                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        @forelse($returnDetails as $index => $detail)
                                            <tr wire:key="return-desktop-{{ $detail['id'] }}" class="align-top hover:bg-slate-50/70 dark:hover:bg-slate-800/50">
                                                <td class="px-4 py-4">
                                                    <span class="inline-flex rounded-md bg-slate-100 px-2 py-1 text-[9px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $detail['type'] }}</span>
                                                </td>

                                                <td class="px-4 py-4 font-bold text-brand-600 dark:text-brand-400">#{{ $detail['code'] }}</td>

                                                <td class="px-4 py-4">
                                                    <div class="max-w-[190px] break-words font-bold leading-relaxed text-slate-800 dark:text-white">{{ $detail['name'] }}</div>
                                                </td>

                                                <td class="px-4 py-4">
                                                    @if($detail['type'] === 'Ruangan')
                                                        <div class="flex max-w-[230px] flex-wrap gap-1">
                                                            @forelse($detail['fasilitas'] ?? [] as $facility)
                                                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-[8px] font-semibold text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300">
                                                                    <i class="fa-solid {{ $this->facilityIcon($facility) }}"></i>
                                                                    {{ $facility }}
                                                                </span>
                                                            @empty
                                                                <span class="text-[9px] text-slate-400">Tidak ada</span>
                                                            @endforelse
                                                        </div>
                                                    @else
                                                        <span class="text-[9px] text-slate-400">-</span>
                                                    @endif
                                                </td>

                                                <td class="px-4 py-4 text-center font-extrabold text-slate-800 dark:text-white">{{ $detail['jumlah'] }}</td>
                                                {{-- catatan admin view only --}}
                                                <td class="px-4 py-4">
                                                    <div class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-[10px] outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                                        {{ $detail['catatan'] }}
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <input type="text" wire:model="returnNotes.{{ $index }}" placeholder="Catatan item..." class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-[10px] outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                                    @error('returnNotes.'.$index)
                                                        <span class="mt-1 block text-[9px] font-semibold text-rose-500">{{ $message }}</span>
                                                    @enderror
                                                </td>
                                                <td class="px-4 py-4">
                                                    <input type="file" wire:model="returnUploads.{{ $index }}" data-compress-return accept="application/pdf,image/*" class="block w-full text-[10px] text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-[10px] file:font-bold file:text-brand-700 dark:file:bg-brand-900/30 dark:file:text-brand-300">
                                                    <p class="mt-1.5 flex items-start gap-1.5 text-[9px] leading-relaxed text-slate-400">
                                                        <i class="mt-0.5 fa-solid fa-circle-info text-brand-500"></i>
                                                        Foto dokumentasi kondisi barang/ruangan saat dikembalikan.
                                                    </p>

                                                    <div wire:loading wire:target="returnUploads.{{ $index }}" class="mt-1.5 text-[9px] font-semibold text-brand-600 dark:text-brand-400">
                                                        <i class="mr-1 fa-solid fa-spinner animate-spin"></i>
                                                        Memproses file...
                                                    </div>

                                                    @if(!empty($returnUploads[$index]))
                                                        <div class="mt-1.5 flex items-start gap-1.5 break-all text-[9px] font-semibold text-emerald-600 dark:text-emerald-300">
                                                            <i class="mt-0.5 fa-solid fa-circle-check"></i>
                                                            <span>{{ $returnUploads[$index]->getClientOriginalName() }}</span>
                                                        </div>
                                                    @endif

                                                    @error('returnUploads.'.$index)
                                                        <span class="mt-1 block text-[9px] font-semibold text-rose-500">{{ $message }}</span>
                                                    @enderror
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="p-10 text-center">
                                                    <div class="flex flex-col items-center">
                                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-300 dark:bg-slate-800 dark:text-slate-600">
                                                            <i class="text-sm fa-solid fa-inbox"></i>
                                                        </div>
                                                        <span class="mt-2 text-[10px] font-medium text-slate-400">Tidak ada item yang berstatus Disetujui.</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-800/60">
                        <label class="mb-2 flex items-center gap-2 text-xs font-extrabold text-slate-700 dark:text-slate-200">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                                <i class="text-[10px] fa-solid fa-flag-checkered"></i>
                            </span>
                            Status Peminjaman
                        </label>

                        <select wire:model="returnStatus" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold text-slate-700 outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-900/50 dark:text-white">
                            <option value="Dikembalikan">Dikembalikan</option>
                            <option value="Selesai">Selesai</option>
                        </select>

                        @error('returnStatus')
                            <span class="mt-1 block text-[10px] font-semibold text-rose-500">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="sticky bottom-0 shrink-0 border-t border-slate-100 bg-white/95 px-4 py-3.5 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95 sm:px-6">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" wire:click="closeReturn" class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-slate-100 px-5 text-xs font-bold text-slate-700 transition hover:bg-slate-200 sm:w-auto dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                            Tutup
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="submitReturn,returnUploads" class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-brand-600 px-5 text-xs font-bold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                            <span wire:loading.remove wire:target="submitReturn">
                                <i class="mr-1.5 fa-solid fa-check"></i>
                                Simpan Pengembalian
                            </span>
                            <span wire:loading wire:target="submitReturn">
                                <i class="mr-1.5 fa-solid fa-spinner animate-spin"></i>
                                Menyimpan...
                            </span>
                        </button>
                    </div>
                </div>
            </form>
            </div>
        </div>
    </template>

    {{-- MODAL BATAL --}}
    <template x-teleport="body">
        <div x-data="{ open: @entangle('isCancelModalOpen') }" x-show="open" x-cloak class="fixed inset-0 z-[130] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm"></div>
            <div x-show="open" x-transition class="relative w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 text-rose-500 dark:bg-rose-900/30"><i class="text-xl fa-solid fa-triangle-exclamation"></i></div>
                <h3 class="mt-4 text-center text-lg font-bold text-slate-900 dark:text-white">Batalkan Pengajuan?</h3>
                @error('cancel')<div class="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-600 dark:bg-rose-900/20 dark:text-rose-300">{{ $message }}</div>@enderror
                <p class="mt-2 text-center text-sm leading-relaxed text-slate-500 dark:text-slate-400">Apakah Anda yakin ingin membatalkan pengajuan <b>{{ $selectedBorrowing['kode_transaksi'] ?? '-' }}</b>?</p>
                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <button type="button" wire:click="closeCancel" class="rounded-xl bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">Kembali</button>
                        <button type="button" wire:click="cancelBorrowing" wire:loading.attr="disabled" wire:target="cancelBorrowing" class="rounded-xl bg-rose-600 px-4 py-3 text-sm font-bold text-white disabled:opacity-60">
                            <span wire:loading.remove wire:target="cancelBorrowing">Ya, Batalkan</span>
                            <span wire:loading wire:target="cancelBorrowing"><i class="mr-1 fa-solid fa-spinner animate-spin"></i>Memproses...</span>
                        </button>
                    </div>
            </div>
        </div>
    </template>

    {{-- MODAL PRINT --}}
    <template x-teleport="body">
        <div
            x-data="{ open: @entangle('isPrintModalOpen') }"
            x-show="open"
            x-cloak
            class="print-modal fixed inset-0 z-[150] flex items-center justify-center p-2 sm:p-4"
        >
            <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm no-print"></div>
            <div x-show="open" x-transition class="relative flex w-full max-w-4xl max-h-[96vh] flex-col overflow-hidden rounded-2xl sm:rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
                <div class="no-print flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3.5 sm:px-6 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                            <i class="text-sm fa-solid fa-print"></i>
                        </div>

                        <div class="min-w-0">
                            <h3 class="text-sm font-extrabold text-slate-900 sm:text-base dark:text-white">
                                Preview Dokumen Peminjaman
                            </h3>

                            <p class="mt-0.5 truncate text-[9px] font-semibold text-brand-600 sm:text-[10px] dark:text-brand-400">
                                {{ $selectedBorrowing['kode_transaksi'] ?? '-' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            onclick="printBorrowingDocument()"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-xl bg-brand-600 px-3.5 text-[10px] font-bold text-white shadow-sm transition hover:bg-brand-700 sm:h-10 sm:px-4 sm:text-xs"
                        >
                            <i class="fa-solid fa-print"></i>
                            <span class="hidden sm:inline">Cetak / Simpan PDF</span>
                            <span class="sm:hidden">Cetak</span>
                        </button>

                        <button
                            type="button"
                            wire:click="closePrint"
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition hover:bg-rose-50 hover:text-rose-500 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-rose-500/10"
                            aria-label="Tutup"
                        >
                            <i class="text-xs fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>

                <div id="history-print-preview" class="min-h-0 flex-1 overflow-y-auto hide-scrollbar bg-slate-950 p-3 sm:p-6">
                    <div id="history-print-card" class="mx-auto w-full max-w-md overflow-hidden rounded-[1.75rem] bg-white text-slate-900 shadow-2xl print-shadow">

                        <div class="relative overflow-hidden bg-gradient-to-br from-brand-600 to-indigo-700 px-5 py-6 text-center text-white sm:px-6">
                            <div class="absolute -right-12 -top-12 h-32 w-32 rounded-full bg-white/10"></div>
                            <div class="absolute -bottom-16 -left-16 h-32 w-32 rounded-full bg-white/5"></div>

                            <div class="relative">
                                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl border border-white/20 bg-white/15">
                                    <i class="text-2xl fa-solid fa-id-card"></i>
                                </div>

                                <div class="text-[9px] font-bold uppercase tracking-[0.28em] text-indigo-100">
                                    Kartu Peminjaman
                                </div>

                                <div class="mt-2 break-all text-xl font-black tracking-tight sm:text-2xl">
                                    {{ $selectedBorrowing['kode_transaksi'] ?? '-' }}
                                </div>
                            </div>
                        </div>

                        <div class="p-5 sm:p-6">
                            <div class="grid grid-cols-2 gap-x-4 gap-y-4 text-xs">
                                <div class="min-w-0">
                                    <div class="text-[8px] font-bold uppercase tracking-wide text-slate-400">Peminjam</div>
                                    <div class="mt-1 break-words font-bold">{{ $selectedBorrowing['nama'] ?? '-' }}</div>
                                </div>

                                <div class="min-w-0">
                                    <div class="text-[8px] font-bold uppercase tracking-wide text-slate-400">No. HP</div>
                                    <div class="mt-1 break-words font-bold">{{ $selectedBorrowing['no_hp'] ?? '-' }}</div>
                                </div>

                                <div class="min-w-0">
                                    <div class="text-[8px] font-bold uppercase tracking-wide text-slate-400">Mulai</div>
                                    <div class="mt-1 break-words font-bold">{{ $selectedBorrowing['tanggal_mulai'] ?? '-' }}</div>
                                </div>

                                <div class="min-w-0">
                                    <div class="text-[8px] font-bold uppercase tracking-wide text-slate-400">Selesai</div>
                                    <div class="mt-1 break-words font-bold">{{ $selectedBorrowing['tanggal_selesai'] ?? '-' }}</div>
                                </div>
                            </div>

                            @if(!empty($selectedBorrowing['tujuan']))
                                <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="text-[8px] font-bold uppercase tracking-wide text-slate-400">Tujuan / Keperluan</div>
                                    <div class="mt-1.5 break-words text-[10px] font-semibold leading-relaxed text-slate-700 sm:text-xs">
                                        {{ $selectedBorrowing['tujuan'] }}
                                    </div>
                                </div>
                            @endif

                            <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-[8px] font-bold uppercase tracking-wide text-slate-400">
                                        Fasilitas
                                    </div>

                                    <div class="text-[8px] font-bold text-slate-400">
                                        {{ count($selectedBorrowing['details'] ?? []) }} item
                                    </div>
                                </div>

                                <div class="mt-3 space-y-3">
                                    @forelse($selectedBorrowing['details'] ?? [] as $detail)
                                        <div class="border-b border-slate-200 pb-3 last:border-0 last:pb-0">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0 flex-1">
                                                    <div class="break-words text-[11px] font-extrabold text-slate-800">
                                                        {{ $detail['name'] }}
                                                    </div>

                                                    <div class="mt-0.5 text-[8px] font-medium text-slate-400">
                                                        {{ $detail['type'] }} · #{{ $detail['code'] }}
                                                    </div>
                                                </div>

                                                <span class="shrink-0 text-[10px] font-extrabold text-slate-800">
                                                    ×{{ $detail['jumlah'] }}
                                                </span>
                                            </div>

                                            @if($detail['type'] === 'Ruangan')
                                                <div class="mt-2 flex flex-wrap gap-1.5">
                                                    @forelse($detail['fasilitas'] ?? [] as $facility)
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-[8px] font-semibold text-emerald-700">
                                                            <i class="fa-solid {{ $this->facilityIcon($facility) }}"></i>
                                                            {{ $facility }}
                                                        </span>
                                                    @empty
                                                        <span class="text-[8px] text-slate-400">
                                                            Tidak ada fasilitas.
                                                        </span>
                                                    @endforelse
                                                </div>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="py-4 text-center text-[9px] text-slate-400">
                                            Tidak ada rincian fasilitas.
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="mt-5 border-t border-slate-200 pt-5">
                                <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-center">
                                    <div class="shrink-0 rounded-xl border border-slate-200 bg-white p-2">
                                        <img
                                            src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($selectedBorrowing['kode_transaksi'] ?? '') }}"
                                            alt="QR Code {{ $selectedBorrowing['kode_transaksi'] ?? '' }}"
                                            class="h-28 w-28 sm:h-32 sm:w-32"
                                        >
                                    </div>

                                    <div class="min-w-0 text-center sm:text-left">
                                        <div class="text-[10px] font-extrabold text-slate-800 sm:text-xs">
                                            Tunjukkan kartu ini
                                        </div>

                                        <div class="mt-1 text-[9px] leading-relaxed text-slate-500 sm:text-[10px]">
                                            QR Code digunakan untuk verifikasi transaksi kepada petugas.
                                        </div>

                                        <div class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[8px] font-bold text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            {{ $selectedBorrowing['status'] ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if(!empty($selectedBorrowing['catatan_admin']))
                                <div class="mt-4 rounded-xl bg-amber-50 p-3 text-[9px] leading-relaxed text-amber-800">
                                    <span class="font-bold">Catatan Admin:</span>
                                    {{ $selectedBorrowing['catatan_admin'] }}
                                </div>
                            @endif

                            @if(!empty($selectedBorrowing['catatan_pengembalian']))
                                <div class="mt-3 rounded-xl bg-emerald-50 p-3 text-[9px] leading-relaxed text-emerald-800">
                                    <span class="font-bold">Catatan Pengembalian:</span>
                                    {{ $selectedBorrowing['catatan_pengembalian'] }}
                                </div>
                            @endif

                            @if(!empty($selectedBorrowing['file_bukti_pengembalian']))
                                <div class="mt-3 rounded-xl bg-slate-50 p-3 text-[9px] text-slate-600">
                                    <span class="font-bold">Bukti Pengembalian:</span>
                                    Tersedia
                                </div>
                            @endif

                            <div class="mt-5 border-t border-slate-200 pt-4 text-center">
                                <div class="text-[8px] font-medium text-slate-400">
                                    Dokumen ini dibuat secara elektronik.
                                </div>
                                <div class="mt-0.5 text-[8px] text-slate-400">
                                    Kode transaksi: {{ $selectedBorrowing['kode_transaksi'] ?? '-' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <script>
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

        
        async function printBorrowingDocument() {
            const card = document.getElementById('history-print-card');

            if (!card) {
                console.error('Elemen history-print-card tidak ditemukan.');
                return;
            }

            // Ambil seluruh stylesheet yang sedang digunakan halaman
            const styles = Array.from(
                document.querySelectorAll('link[rel="stylesheet"], style')
            )
            .map(el => el.outerHTML)
            .join('\n');

            // Clone preview
            const printCard = card.cloneNode(true);

            // Buat window khusus untuk print
            const printWindow = window.open(
                '',
                '_blank',
                'width=1000,height=900'
            );

            if (!printWindow) {
                alert('Popup diblokir browser. Izinkan popup untuk mencetak PDF.');
                return;
            }

            printWindow.document.open();

            printWindow.document.write(`
                <!DOCTYPE html>
                <html lang="id">
                <head>
                    <meta charset="UTF-8">

                    <title>
                        ${getPrintTitle()}
                    </title>

                    ${styles}

                    <style>
                        @page {
                            size: A4 portrait;
                            margin: 10mm;
                        }

                        * {
                            -webkit-print-color-adjust: exact !important;
                            print-color-adjust: exact !important;
                        }

                        html,
                        body {
                            margin: 0;
                            padding: 0;
                            background: #ffffff !important;
                        }

                        body {
                            min-height: 100vh;
                            font-family: Arial, sans-serif;
                        }

                        .print-wrapper {
                            width: 100%;
                            min-height: 100vh;
                            display: flex;
                            justify-content: center;
                            align-items: flex-start;
                            padding: 5mm 0;
                        }

                        #history-print-card {
                            width: 100%;
                            max-width: 108mm;
                            margin: 0 auto !important;

                            overflow: hidden;

                            box-shadow: none !important;

                            /* Pertahankan tampilan preview */
                            -webkit-print-color-adjust: exact !important;
                            print-color-adjust: exact !important;

                            break-inside: avoid;
                            page-break-inside: avoid;
                        }

                        img {
                            -webkit-print-color-adjust: exact !important;
                            print-color-adjust: exact !important;
                        }

                        /*
                        * Jangan ikutkan tombol/modal.
                        */
                        .no-print {
                            display: none !important;
                        }

                        /*
                        * Pastikan tidak ada scrollbar.
                        */
                        body,
                        html {
                            overflow: visible !important;
                        }

                        @media print {
                            body {
                                background: white !important;
                            }

                            .print-wrapper {
                                padding: 0;
                            }

                            #history-print-card {
                                break-inside: avoid;
                                page-break-inside: avoid;
                            }
                        }
                    </style>
                </head>

                <body>

                    <div class="print-wrapper">
                        ${printCard.outerHTML}
                    </div>

                </body>
                </html>
            `);

            printWindow.document.close();

            /*
            * Tunggu semua gambar selesai loading,
            * terutama QR Code.
            */
            await waitForImages(printWindow);

            /*
            * Tunggu font/style selesai.
            */
            try {
                if (printWindow.document.fonts) {
                    await printWindow.document.fonts.ready;
                }
            } catch (e) {
                console.warn('Font loading warning:', e);
            }

            /*
            * Beri waktu browser merender layout.
            */
            setTimeout(() => {
                printWindow.focus();
                printWindow.print();

                /*
                * Tutup window setelah dialog print selesai.
                */
                printWindow.onafterprint = () => {
                    printWindow.close();
                };
            }, 500);
        }

        function getPrintTitle() {
            const transaction =
                @json($selectedBorrowing['kode_transaksi'] ?? 'peminjaman');

            return `Kartu Peminjaman - ${transaction}`;
        }

        function waitForImages(win) {
            return new Promise((resolve) => {
                const images = Array.from(
                    win.document.images
                );

                if (!images.length) {
                    resolve();
                    return;
                }

                let loaded = 0;

                const done = () => {
                    loaded++;

                    if (loaded >= images.length) {
                        resolve();
                    }
                };

                images.forEach((img) => {

                    if (img.complete) {
                        done();
                        return;
                    }

                    img.onload = done;
                    img.onerror = done;
                });

                /*
                * Fallback agar tidak menggantung
                * jika QR/image eksternal bermasalah.
                */
                setTimeout(resolve, 5000);
            });
        }
    </script>
    <x-toast />
</div>

