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

        foreach ($admins as $admin) {
            SystemNotification::create([
                'user_id' => $admin->id,
                'title' => 'Peminjaman Dibatalkan',
                'message' => "{$userName} membatalkan pengajuan peminjaman {$resourceName}. Transaksi {$borrowing->kode_transaksi} telah dibatalkan.",
                'url' => route('admin.booking'),
                'is_read' => false,
            ]);
        }
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

        SystemNotification::create([
            'user_id' => $borrowing->approved_by,
            'title' => 'Pengembalian Peminjaman',
            'message' => "Pengembalian {$resourceName} oleh " . (auth()->user()?->name ?? 'User') . " telah diajukan untuk transaksi {$borrowing->kode_transaksi}. Silakan periksa pengembalian tersebut.",
            'url' => route('admin.booking'),
            'is_read' => false,
        ]);
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
                'file' => $detail->getAttribute('bukti_pengembalian') ?? $detail->getAttribute('file_bukti_pengembalian') ?? null,
                'catatan_pengembalian' => $detail->getAttribute('catatan_pengembalian') ?? '',
            ])->values()->toArray();

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
            if (array_key_exists($index, $this->returnUploads) && $this->returnUploads[$index]) {
                $rules["returnUploads.$index"] = ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:1024'];
            }
            $rules["returnNotes.$index"] = ['nullable', 'string', 'max:2000'];
        }

        $this->validate($rules, [
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
        $this->dispatch('toast', type: 'success', message: 'Pengembalian per item berhasil disimpan.');
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
            'details' => $borrowing->details->map(fn (BorrowingDetail $detail) => [
                'id' => $detail->id,
                'type' => $detail->room ? 'Ruangan' : 'Barang',
                'name' => $detail->room?->nama_ruangan ?? $detail->item?->nama_barang ?? '-',
                'code' => $detail->room?->kode_ruangan ?? $detail->item?->kode_barang ?? '-',
                'jumlah' => (int) $detail->jumlah,
                'status' => $detail->status,
                'catatan' => $detail->getAttribute('catatan') ?? '',
                'file_bukti_pengembalian' => $detail->getAttribute('file_bukti_pengembalian') ?? null,
                'catatan_pengembalian' => $detail->getAttribute('catatan_pengembalian') ?? '',
            ])->values()->toArray(),
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
        <div wire:loading.flex wire:target="search,statusFilter,gotoPage,nextPage,previousPage" class="absolute inset-0 z-30 items-start justify-center rounded-3xl bg-white/70 px-4 pt-16 backdrop-blur-[2px] dark:bg-slate-900/70">
            <div class="flex items-center gap-3 rounded-2xl border border-brand-100 bg-white px-4 py-3 text-xs font-bold text-brand-600 shadow-lg dark:border-brand-900/50 dark:bg-slate-900 dark:text-brand-400">
                <span class="flex h-7 w-7 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-900/30"><i class="fa-solid fa-spinner animate-spin"></i></span>
                <span>Memuat riwayat...</span>
            </div>
        </div>

        <div id="history-print-area" class="space-y-4">
            @forelse($borrowings as $borrowing)
                @php($status = $borrowing->status ?? '-')
                <article wire:key="history-{{ $borrowing->id }}" class="overflow-hidden border shadow-sm rounded-3xl border-slate-200 bg-white dark:bg-slate-800 dark:border-slate-700">
                    <div class="p-4 sm:p-5 lg:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-2.5 py-1 text-[10px] font-bold text-brand-600 dark:bg-brand-900/30 dark:text-brand-400">
                                        <i class="text-[9px] fa-solid fa-hashtag"></i>{{ $borrowing->kode_transaksi }}
                                    </span>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold {{ $this->statusClass($status) }}">{{ $status }}</span>
                                </div>
                                <h3 class="mt-2 text-sm font-bold leading-relaxed break-words text-slate-900 sm:text-base dark:text-white">{{ $borrowing->tujuan ?: 'Peminjaman fasilitas' }}</h3>
                            </div>
                            <div class="w-full shrink-0 rounded-xl bg-slate-50 px-3 py-2 sm:w-fit lg:min-w-[220px] lg:text-right dark:bg-slate-900/60">
                                <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Periode Peminjaman</div>
                                <div class="mt-1 text-xs font-semibold text-slate-800 dark:text-slate-200">{{ optional($borrowing->tanggal_mulai)->format('d M Y H:i') }}</div>
                                <div class="text-[10px] text-slate-400">s/d {{ optional($borrowing->tanggal_selesai)->format('d M Y H:i') }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 mt-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/60">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm dark:bg-slate-800 dark:text-slate-400"><i class="text-xs fa-solid fa-user"></i></span>
                                    <div class="min-w-0">
                                        <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Peminjam</div>
                                        <div class="mt-0.5 truncate text-xs font-bold text-slate-800 dark:text-slate-200">{{ $borrowing->user?->name ?? '-' }}</div>
                                    </div>
                                </div>
                                <div class="mt-2 text-[10px] text-slate-400">{{ $borrowing->user?->no_hp ?? $borrowing->user?->no_wa ?? '-' }}</div>
                            </div>

                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/60">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm dark:bg-slate-800 dark:text-slate-400"><i class="text-xs fa-solid fa-list-check"></i></span>
                                    <div>
                                        <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Detail Peminjaman</div>
                                        <div class="mt-0.5 text-xs font-bold text-slate-800 dark:text-slate-200">{{ $borrowing->details->count() }} item</div>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/60">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm dark:bg-slate-800 dark:text-slate-400"><i class="text-xs fa-solid fa-paperclip"></i></span>
                                    <div class="min-w-0">
                                        <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Lampiran</div>
                                        <div class="mt-0.5 truncate text-xs font-bold text-slate-800 dark:text-slate-200">{{ $borrowing->file_lampiran ? 'Lampiran tersedia' : 'Tidak ada lampiran' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 pt-4 mt-4 border-t border-slate-100 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between dark:border-slate-700">
                            <div class="flex items-center gap-2 text-[10px] text-slate-400"><i class="fa-solid fa-circle-info"></i><span>Periksa detail transaksi dan fasilitas yang dipinjam.</span></div>
                            <div class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap">
                                <button type="button" wire:click="openDetail({{ $borrowing->id }})" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600">
                                    <i class="fa-solid fa-eye"></i> Detail
                                </button>

                                @if($borrowing->file_lampiran)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($borrowing->file_lampiran) }}" target="_blank" rel="noopener" class="no-print inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                                        <i class="fa-solid fa-paperclip"></i> Lampiran
                                    </a>
                                @endif

                                @if($status === 'Menunggu')
                                    <button type="button" wire:click="openCancel({{ $borrowing->id }})" class="no-print inline-flex items-center justify-center gap-2 rounded-xl bg-rose-50 px-4 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-100 dark:bg-rose-900/20 dark:text-rose-300">
                                        <i class="fa-solid fa-ban"></i> Batalkan
                                    </button>
                                @elseif($status === 'Disetujui')
                                    <button type="button" wire:click="openReturn({{ $borrowing->id }})" class="no-print inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-emerald-700">
                                        <i class="fa-solid fa-box-open"></i> Pengembalian
                                    </button>
                                    <button type="button" wire:click="openPrint({{ $borrowing->id }})" class="no-print inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-brand-700">
                                        <i class="fa-solid fa-print"></i> Cetak
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center dark:border-slate-700 dark:bg-slate-800">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-900"><i class="text-xl fa-solid fa-clock-rotate-left"></i></div>
                    <h3 class="mt-4 text-sm font-bold text-slate-700 dark:text-slate-200">Belum ada riwayat peminjaman</h3>
                    <p class="mt-1 text-xs text-slate-400">Data peminjaman kamu akan muncul di halaman ini.</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-6">{{ $borrowings->links() }}</div>

    {{-- MODAL DETAIL --}}
    <template x-teleport="body">
        <div x-data="{ open: @entangle('isDetailModalOpen') }" x-show="open" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-3 sm:p-5">
            <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm"></div>
            <div x-show="open" x-transition class="hide-scrollbar relative flex w-full max-w-4xl max-h-[94vh] flex-col overflow-hidden rounded-2xl sm:rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 bg-white/95 px-4 py-4 backdrop-blur sm:px-6 dark:border-slate-800 dark:bg-slate-900/95">
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-slate-900 sm:text-lg dark:text-white">Detail Peminjaman</h2>
                        <p class="truncate text-[10px] font-bold text-brand-600 sm:text-xs dark:text-brand-400">{{ $selectedBorrowing['kode_transaksi'] ?? '-' }}</p>
                    </div>
                    <button type="button" wire:click="closeDetail" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 hover:text-rose-500 dark:bg-slate-800"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto hide-scrollbar">
                    <div class="space-y-5 p-4 sm:p-6">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Status</div><div class="mt-2"><span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold {{ $this->statusClass($selectedBorrowing['status'] ?? '') }}">{{ $selectedBorrowing['status'] ?? '-' }}</span></div></div>
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Peminjam</div><div class="mt-1 truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $selectedBorrowing['nama'] ?? '-' }}</div><div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $selectedBorrowing['no_hp'] ?? '-' }}</div></div>
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Mulai</div><div class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $selectedBorrowing['tanggal_mulai'] ?? '-' }}</div></div>
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Selesai</div><div class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $selectedBorrowing['tanggal_selesai'] ?? '-' }}</div></div>
                        </div>

                        <div>
                            <div class="mb-2 text-xs font-bold text-slate-700 dark:text-slate-300">Tujuan / Keperluan</div>
                            <div class="break-words rounded-2xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $selectedBorrowing['tujuan'] ?? '-' }}</div>
                        </div>

                        <div >
                            <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4 dark:border-amber-900/30 dark:bg-amber-900/20">
                                <div class="flex items-center gap-2 text-xs font-bold text-amber-800 dark:text-amber-200"><i class="fa-solid fa-note-sticky"></i> Catatan Admin</div>
                                <div class="mt-2 break-words text-sm leading-relaxed text-amber-800 dark:text-amber-200">{{ $selectedBorrowing['catatan_admin'] ?? 'Tidak ada catatan admin.' }}</div>
                            </div>
                            
                        </div>
                        <div>
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div class="text-xs font-bold text-slate-700 dark:text-slate-300">Fasilitas yang Dipinjam</div>
                                <div class="text-[10px] font-medium text-slate-400">{{ count($selectedBorrowing['details'] ?? []) }} item</div>
                            </div>
                            <div class="hidden md:block">
                                <div class=" rounded-2xl border border-slate-200 dark:border-slate-800">
                                    {{-- Hint geser --}} 
                                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-3 py-2 text-[10px] text-slate-400 dark:border-slate-800 dark:bg-slate-800/70"> 
                                        <span><i class="fa-solid fa-arrows-left-right mr-1"></i> Geser tabel untuk melihat detail lainnya </span> 
                                    </div>
                                    <div class="overflow-x-auto overflow-y-hidden hide-scrollbar">
                                        <table class="min-w-[1050px] w-full text-left text-xs">
                                            <thead class="bg-slate-50 dark:bg-slate-800">
                                                <tr>
                                                    <th class="w-[120px] px-4 py-3 font-bold uppercase tracking-wide text-slate-400">Tipe</th>
                                                    <th class="w-[130px] px-4 py-3 font-bold uppercase tracking-wide text-slate-400">Kode</th>
                                                    <th class="w-[220px] px-4 py-3 font-bold uppercase tracking-wide text-slate-400">Nama</th>
                                                    <th class="w-[80px] px-4 py-3 text-center font-bold uppercase tracking-wide text-slate-400">Qty</th>
                                                    <th class="w-[130px] px-4 py-3 text-center font-bold uppercase tracking-wide text-slate-400">Status</th>
                                                    <th class="w-[220px] px-4 py-3 font-bold uppercase tracking-wide text-slate-400">Catatan</th>
                                                    <th class="w-[240px] px-4 py-3 font-bold uppercase tracking-wide text-slate-400">Pengembalian</th>
                                                    <th class="w-[90px] px-4 py-3 text-center font-bold uppercase tracking-wide text-slate-400">Bukti</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                @forelse(($selectedBorrowing['details'] ?? []) as $detail)
                                                    <tr class="align-top transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/50">
                                                        <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                                            <span class="inline-flex rounded-lg bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $detail['type'] }}</span>
                                                        </td>
                                                        <td class="px-4 py-4 font-semibold text-brand-600 dark:text-brand-400">#{{ $detail['code'] }}</td>
                                                        <td class="px-4 py-4">
                                                            <div class="max-w-[200px] break-words font-semibold leading-relaxed text-slate-900 dark:text-white">{{ $detail['name'] }}</div>
                                                        </td>
                                                        <td class="px-4 py-4 text-center">
                                                            <span class="font-bold text-slate-900 dark:text-white">{{ $detail['jumlah'] }}</span>
                                                        </td>
                                                        <td class="px-4 py-4 text-center">
                                                            <span class="inline-flex max-w-full whitespace-nowrap rounded-full px-2.5 py-1 text-[9px] font-bold {{ $this->statusClass($detail['status']) }}">{{ $detail['status'] }}</span>
                                                        </td>
                                                        <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                                            <div class="max-w-[210px] break-words leading-relaxed">{{ $detail['catatan'] ?: '-' }}</div>
                                                        </td>
                                                        <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                                            <div class="max-w-[230px] break-words leading-relaxed">{{ $detail['catatan_pengembalian'] ?: '-' }}</div>
                                                        </td>
                                                        <td class="px-4 py-4 text-center">
                                                            @if(!empty($detail['file_bukti_pengembalian']))
                                                                <a href="{{ \Illuminate\Support\Facades\Storage::url($detail['file_bukti_pengembalian']) }}" target="_blank" rel="noopener" title="Lihat bukti pengembalian" class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 transition hover:bg-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-300 dark:hover:bg-emerald-900/40">
                                                                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                                                </a>
                                                            @else
                                                                <span class="text-slate-400">-</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="8" class="p-10 text-center text-slate-400">
                                                            <div class="flex flex-col items-center justify-center">
                                                                <i class="fa-solid fa-box-open mb-2 text-xl text-slate-300 dark:text-slate-600"></i>
                                                                <span>Tidak ada rincian fasilitas.</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 md:hidden">
                                @forelse(($selectedBorrowing['details'] ?? []) as $detail)
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="rounded-md bg-slate-100 px-2 py-1 text-[9px] font-bold uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $detail['type'] }}</span>
                                                    <span class="text-[10px] font-semibold text-brand-600 dark:text-brand-400">#{{ $detail['code'] }}</span>
                                                </div>
                                                <div class="mt-1.5 break-words text-sm font-bold text-slate-900 dark:text-white">{{ $detail['name'] }}</div>
                                            </div>
                                            <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-[9px] font-bold {{ $this->statusClass($detail['status']) }}">{{ $detail['status'] }}</span>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-3">
                                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                                                <div class="text-[9px] font-bold uppercase text-slate-400">Jumlah</div>
                                                <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ $detail['jumlah'] }}</div>
                                            </div>
                                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                                                <div class="text-[9px] font-bold uppercase text-slate-400">Bukti</div>
                                                <div class="mt-1.5">
                                                    @if(!empty($detail['file_bukti_pengembalian']))
                                                        <a href="{{ \Illuminate\Support\Facades\Storage::url($detail['file_bukti_pengembalian']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300">
                                                            <i class="fa-solid fa-arrow-up-right-from-square"></i>Lihat
                                                        </a>
                                                    @else
                                                        <span class="text-xs text-slate-400">Tidak ada</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                                            <div class="text-[9px] font-bold uppercase text-slate-400">Catatan</div>
                                            <div class="mt-1 break-words text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ $detail['catatan'] ?: 'Tidak ada catatan.' }}</div>
                                        </div>
                                        <div class="mt-3 rounded-xl bg-emerald-50 p-3 dark:bg-emerald-900/20">
                                            <div class="text-[9px] font-bold uppercase text-emerald-600 dark:text-emerald-300">Catatan Pengembalian</div>
                                            <div class="mt-1 break-words text-xs leading-relaxed text-emerald-800 dark:text-emerald-200">{{ $detail['catatan_pengembalian'] ?: 'Belum ada catatan pengembalian.' }}</div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center dark:border-slate-700">
                                        <i class="fa-solid fa-inbox text-2xl text-slate-300 dark:text-slate-600"></i>
                                        <div class="mt-2 text-xs text-slate-400">Tidak ada rincian fasilitas.</div>
                                    </div>
                                @endforelse
                            </div>
                        </div>


                        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                            @if(!empty($selectedBorrowing['file_lampiran']))
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($selectedBorrowing['file_lampiran']) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-50 px-4 py-2.5 text-xs font-bold text-brand-600 dark:bg-brand-900/20 dark:text-brand-400"><i class="fa-solid fa-paperclip"></i>Lihat Lampiran SP</a>
                            @endif
                            @if(!empty($selectedBorrowing['file_bukti_pengembalian']))
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($selectedBorrowing['file_bukti_pengembalian']) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-50 px-4 py-2.5 text-xs font-bold text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300"><i class="fa-solid fa-file-arrow-up"></i>Lihat Bukti Pengembalian</a>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="shrink-0 border-t border-slate-100 bg-white/95 p-4 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" wire:click="closeDetail" class="w-full rounded-xl bg-slate-100 px-5 py-3 text-sm font-bold text-slate-700 sm:w-auto dark:bg-slate-800 dark:text-slate-200">Tutup</button>
                        {{-- @if(($selectedBorrowing['status'] ?? '') === 'Menunggu')
                            <button type="button" wire:click="openCancel({{ $selectedBorrowing['id'] ?? 0 }})" class="w-full rounded-xl bg-rose-600 px-5 py-3 text-sm font-bold text-white sm:w-auto">Batalkan</button>
                        @elseif(($selectedBorrowing['status'] ?? '') === 'Disetujui')
                            <button type="button" wire:click="openReturn({{ $selectedBorrowing['id'] ?? 0 }})" class="w-full rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white sm:w-auto">Pengembalian</button>
                            <button type="button" wire:click="openPrint({{ $selectedBorrowing['id'] ?? 0 }})" class="w-full rounded-xl bg-brand-600 px-5 py-3 text-sm font-bold text-white sm:w-auto">Cetak</button>
                        @endif --}}
                    </div>
                </div>
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

    {{-- MODAL PENGEMBALIAN --}}
    <template x-teleport="body">
        <div x-data="{ open: @entangle('isReturnModalOpen') }" x-show="open" x-cloak class="fixed inset-0 z-[140] flex items-center justify-center p-3 sm:p-5">
            <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm"></div>
            <div x-show="open" x-transition class="relative hide-scrollbar flex w-full max-w-4xl max-h-[94vh] flex-col overflow-hidden rounded-2xl sm:rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 bg-white/95 px-4 py-4 backdrop-blur sm:px-6 dark:border-slate-800 dark:bg-slate-900/95"><div class="min-w-0"><h3 class="text-base font-bold text-slate-900 sm:text-lg dark:text-white">Pengembalian Peminjaman</h3><p class="truncate text-[10px] font-bold text-brand-600 dark:text-brand-400">{{ $selectedBorrowing['kode_transaksi'] ?? '-' }}</p></div><button type="button" wire:click="closeReturn" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 hover:text-rose-500 dark:bg-slate-800"><i class="fa-solid fa-xmark"></i></button></div>

                <form wire:submit="submitReturn" class="min-h-0 flex-1 overflow-y-auto history-scrollbar-hidden">
                    <div class="space-y-5 p-4 sm:p-6">
                        <div class="space-y-3 md:hidden">
                            @forelse($returnDetails as $index => $detail)
                                <div wire:key="return-mobile-{{ $detail['id'] }}" class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                                    <div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="rounded-md bg-slate-100 px-2 py-1 text-[9px] font-bold uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $detail['type'] }}</span><span class="text-[10px] font-semibold text-brand-600 dark:text-brand-400">#{{ $detail['code'] }}</span></div><div class="mt-1.5 text-sm font-bold break-words text-slate-900 dark:text-white">{{ $detail['name'] }}</div></div><span class="shrink-0 rounded-full px-2 py-1 text-[9px] font-bold {{ $this->statusClass($detail['status']) }}">{{ $detail['status'] }}</span></div>
                                    <div class="mt-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800"><div class="text-[9px] font-bold uppercase text-slate-400">Jumlah</div><div class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ $detail['jumlah'] }}</div></div>
                                    <div class="mt-3"><label class="mb-1.5 block text-[10px] font-bold uppercase text-slate-400">Bukti Pengembalian</label><input type="file" wire:model="returnUploads.{{ $index }}" data-compress-return accept="application/pdf,image/*" capture="environment" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-bold file:text-indigo-700 dark:file:bg-indigo-900/30 dark:file:text-indigo-300"><div wire:loading wire:target="returnUploads.{{ $index }}" class="mt-1 text-[10px] font-semibold text-indigo-600"><i class="mr-1 fa-solid fa-spinner animate-spin"></i>Memproses file...</div>@if(!empty($returnUploads[$index]))<div class="mt-1 break-all text-[10px] text-emerald-600 dark:text-emerald-300"><i class="mr-1 fa-solid fa-circle-check"></i>{{ $returnUploads[$index]->getClientOriginalName() }}</div>@endif @error('returnUploads.'.$index)<span class="block mt-1 text-[10px] text-rose-500">{{ $message }}</span>@enderror</div>
                                    <div class="mt-3"><label class="mb-1.5 block text-[10px] font-bold uppercase text-slate-400">Catatan Pengembalian</label><input type="text" wire:model="returnNotes.{{ $index }}" placeholder="Catatan item..." class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-white">@error('returnNotes.'.$index)<span class="block mt-1 text-[10px] text-rose-500">{{ $message }}</span>@enderror</div>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-xs text-slate-400 dark:border-slate-700">Tidak ada item yang berstatus Disetujui.</div>
                            @endforelse
                        </div>

                        <div class="hidden overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 md:block">
                            <div class="overflow-x-auto hide-scrollbar">
                                <table class="w-full min-w-[900px] text-xs text-left">
                                    <thead class="bg-slate-50 dark:bg-slate-800"><tr><th class="p-3">Tipe</th><th class="p-3">Kode</th><th class="p-3">Nama</th><th class="p-3 text-center">Jumlah</th><th class="p-3">Bukti Pengembalian</th><th class="p-3">Catatan Pengembalian</th></tr></thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        @forelse($returnDetails as $index => $detail)
                                            <tr wire:key="return-desktop-{{ $detail['id'] }}" class="align-top"><td class="p-3">{{ $detail['type'] }}</td><td class="p-3">#{{ $detail['code'] }}</td><td class="p-3 font-semibold">{{ $detail['name'] }}</td><td class="p-3 text-center">{{ $detail['jumlah'] }}</td><td class="p-3 min-w-[280px]"><input type="file" wire:model="returnUploads.{{ $index }}" data-compress-return accept="application/pdf,image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-bold file:text-indigo-700 dark:file:bg-indigo-900/30 dark:file:text-indigo-300"><div wire:loading wire:target="returnUploads.{{ $index }}" class="mt-1 text-[10px] font-semibold text-indigo-600"><i class="mr-1 fa-solid fa-spinner animate-spin"></i>Memproses file...</div>@if(!empty($returnUploads[$index]))<div class="mt-1 break-all text-[10px] text-emerald-600 dark:text-emerald-300">{{ $returnUploads[$index]->getClientOriginalName() }}</div>@endif @error('returnUploads.'.$index)<span class="block mt-1 text-[10px] text-rose-500">{{ $message }}</span>@enderror</td><td class="p-3 min-w-[260px]"><input type="text" wire:model="returnNotes.{{ $index }}" placeholder="Catatan item..." class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-white">@error('returnNotes.'.$index)<span class="block mt-1 text-[10px] text-rose-500">{{ $message }}</span>@enderror</td></tr>
                                        @empty
                                            <tr><td colspan="6" class="p-8 text-center text-slate-400">Tidak ada item yang berstatus Disetujui.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div><label class="mb-2 block text-xs font-bold text-slate-700 dark:text-slate-300">Status Peminjaman</label><select wire:model="returnStatus" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"><option value="Dikembalikan">Dikembalikan</option><option value="Selesai">Selesai</option></select>@error('returnStatus')<span class="mt-1 block text-xs text-rose-500">{{ $message }}</span>@enderror</div>
                    </div>

                    <div class="sticky bottom-0 border-t border-slate-100 bg-white/95 p-4 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95"><div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" wire:click="closeReturn" class="w-full rounded-xl bg-slate-100 px-5 py-3 text-sm font-bold text-slate-700 sm:w-auto dark:bg-slate-800 dark:text-slate-200">Tutup</button><button type="submit" wire:loading.attr="disabled" wire:target="submitReturn,returnUploads" class="w-full rounded-xl bg-brand-600 px-5 py-3 text-sm font-bold text-white disabled:opacity-60 sm:w-auto"><span wire:loading.remove wire:target="submitReturn">Simpan Pengembalian</span><span wire:loading wire:target="submitReturn"><i class="mr-1 fa-solid fa-spinner animate-spin"></i>Sedang menyimpan data...</span></button></div></div>
                </form>
            </div>
        </div>
    </template>

    {{-- MODAL PRINT --}}
    <template x-teleport="body">
        <div x-data="{ open: @entangle('isPrintModalOpen') }" x-show="open" x-cloak class=" hide-scrollbar fixed inset-0 z-[150] flex items-center justify-center p-3 sm:p-5">
            <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm"></div>
            <div x-show="open" x-transition class="hide-scrollbar relative flex w-full max-w-4xl max-h-[94vh] flex-col overflow-hidden rounded-2xl sm:rounded-3xl bg-white shadow-2xl">
                <div class="no-print flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-4 sm:px-6">
                    <h3 class="text-base font-bold text-slate-900 sm:text-lg">Preview Dokumen Peminjaman</h3>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-white">
                            <i class="fa-solid fa-print"></i>
                            <span class="hidden sm:inline">Cetak / PDF</span>
                            <span class="sm:hidden">Cetak</span>
                        </button>
                        <button type="button" wire:click="closePrint" class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
                <div id="history-print-preview" class="min-h-0 overflow-y-auto history-scrollbar-hidden bg-slate-950 p-4 sm:p-8">
                    <div id="history-print-card" class="print-shadow w-full max-w-md mx-auto overflow-hidden bg-white shadow-2xl rounded-[2rem] text-slate-900">
                        <div class="relative px-5 py-6 overflow-hidden text-center bg-gradient-to-br from-brand-600 to-indigo-700 text-white sm:px-6"><div class="absolute w-32 h-32 rounded-full -top-12 -right-12 bg-white/10"></div><div class="relative"><div class="flex items-center justify-center w-14 h-14 mx-auto mb-3 border rounded-2xl bg-white/15 border-white/20"><i class="text-2xl fa-solid fa-id-card"></i></div><div class="text-[10px] font-bold uppercase tracking-[0.3em] text-indigo-100">Kartu Peminjaman</div><div class="mt-2 text-xl font-black tracking-tight break-all sm:text-2xl">{{ $selectedBorrowing['kode_transaksi'] ?? '-' }}</div></div></div>
                        <div class="p-5 sm:p-6">
                            <div class="grid grid-cols-2 gap-4 text-xs">
                                <div>
                                    <div class="text-[9px] font-bold uppercase text-slate-400">Peminjam</div>
                                    <div class="mt-1 font-bold break-words">{{ $selectedBorrowing['nama'] ?? '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-[9px] font-bold uppercase text-slate-400">No. HP</div>
                                    <div class="mt-1 font-bold break-words">{{ $selectedBorrowing['no_hp'] ?? '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-[9px] font-bold uppercase text-slate-400">Mulai</div>
                                    <div class="mt-1 font-bold">{{ $selectedBorrowing['tanggal_mulai'] ?? '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-[9px] font-bold uppercase text-slate-400">Selesai</div>
                                    <div class="mt-1 font-bold">{{ $selectedBorrowing['tanggal_selesai'] ?? '-' }}</div>
                                </div>
                            </div>
                            <div class="p-4 mt-5 border border-slate-200 rounded-2xl bg-slate-50">
                                <div class="text-[9px] font-bold uppercase text-slate-400">Fasilitas</div>
                                <div class="mt-2 space-y-2 text-xs font-semibold">
                                    @foreach($selectedBorrowing['details'] ?? [] as $detail)
                                        <div class="flex items-start justify-between gap-3">
                                            <span class="break-words">{{ $detail['type'] }} · {{ $detail['name'] }}</span>
                                            <span class="shrink-0">×{{ $detail['jumlah'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex flex-col items-center gap-4 pt-5 mt-5 border-t border-slate-200 sm:flex-row">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($selectedBorrowing['kode_transaksi'] ?? '') }}" alt="QR Code" class="w-28 h-28 p-2 bg-white border rounded-xl border-slate-200 sm:w-32 sm:h-32">
                                <div class="text-xs text-center text-slate-500 sm:text-left">
                                    <div class="font-bold text-slate-800">Tunjukkan kartu ini</div>
                                    <div class="mt-1">QR Code digunakan untuk verifikasi transaksi kepada petugas.</div>
                                    <div class="inline-flex mt-3 rounded-full bg-emerald-50 px-2.5 py-1 text-[9px] font-bold text-emerald-700">Status: {{ $selectedBorrowing['status'] ?? '-' }}</div>
                                </div>
                            </div>
                            @if(!empty($selectedBorrowing['catatan_admin']))<div class="p-3 mt-4 text-[10px] text-amber-800 rounded-xl bg-amber-50"><b>Catatan Admin:</b> {{ $selectedBorrowing['catatan_admin'] }}</div>@endif
                            @if(!empty($selectedBorrowing['catatan_pengembalian']))<div class="p-3 mt-3 text-[10px] text-emerald-800 rounded-xl bg-emerald-50"><b>Catatan Pengembalian:</b> {{ $selectedBorrowing['catatan_pengembalian'] }}</div>@endif
                            @if(!empty($selectedBorrowing['file_bukti_pengembalian']))<div class="p-3 mt-3 text-[10px] text-slate-600 rounded-xl bg-slate-50"><b>Bukti Pengembalian:</b> Tersedia</div>@endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <script>
        document.addEventListener('change', async (event) => {
            const input = event.target;
            if (!input.matches('[data-compress-return]') || !input.files?.length) return;
            const file = input.files[0];
            if (!file.type.startsWith('image/') || file.size <= 900 * 1024) return;
            try {
                const bitmap = await createImageBitmap(file);
                const maxSide = 1600;
                const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(bitmap.width * scale));
                canvas.height = Math.max(1, Math.round(bitmap.height * scale));
                canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                let quality = 0.82;
                let blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/webp', quality));
                while (blob && blob.size > 900 * 1024 && quality > 0.45) {
                    quality -= 0.08;
                    blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/webp', quality));
                }
                if (!blob) return;
                const compressed = new File([blob], (file.name.replace(/\.[^.]+$/, '') || 'bukti') + '.webp', { type: 'image/webp' });
                const dt = new DataTransfer();
                dt.items.add(compressed);
                input.files = dt.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (e) {
                console.warn('Kompresi gambar gagal.', e);
            }
        });
    </script>
</div>

