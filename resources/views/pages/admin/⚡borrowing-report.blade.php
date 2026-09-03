<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use App\Models\Borrowing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

new #[Layout('layouts.app')] #[Title('Laporan Peminjaman')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $status = 'Semua';

    public array $statusOptions = [
        'Semua',
        'Menunggu',
        'Disetujui',
        'Ditolak',
        'Dipinjam',
        'Dikembalikan',
        'Selesai',
    ];

    public int $perPage = 15;

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    protected function reportQuery()
    {
        return Borrowing::query()
            ->with([
                'user',
                'details.room',
                'details.item',
            ])
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);

                $query->where(function ($q) use ($search) {
                    $q->where('kode_transaksi', 'like', "%{$search}%")
                        ->orWhere('tujuan', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->when($this->status !== 'Semua', function ($query) {
                $query->where('status', $this->status);
            })
            ->when($this->dateFrom !== '', function ($query) {
                $query->whereDate('tanggal_mulai', '>=', $this->dateFrom);
            })
            ->when($this->dateTo !== '', function ($query) {
                $query->whereDate('tanggal_mulai', '<=', $this->dateTo);
            })
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function with(): array
    {
        return [
            'borrowings' => $this->reportQuery()->paginate($this->perPage),
        ];
    }

    protected function reportData()
    {
        return $this->reportQuery()->get();
    }

    protected function resourceNames(Borrowing $borrowing): string
    {
        return $borrowing->details
            ->map(function ($detail) {
                return $detail->room?->nama_ruangan
                    ?? $detail->item?->nama_barang
                    ?? '-';
            })
            ->filter()
            ->unique()
            ->implode(', ');
    }

    protected function resourceSummary(Borrowing $borrowing): array
    {
        return $borrowing->details->map(function ($detail) {
            return [
                'type' => $detail->room ? 'Ruangan' : 'Barang',
                'name' => $detail->room?->nama_ruangan ?? $detail->item?->nama_barang ?? '-',
                'code' => $detail->room?->kode_ruangan ?? $detail->item?->kode_barang ?? '-',
                'jumlah' => (int) $detail->jumlah,
                'status' => $detail->status ?? '-',
            ];
        })->values()->toArray();
    }

    public function exportCsv()
    {
        $rows = $this->reportData();
        $filename = 'laporan-peminjaman-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'No',
                'Kode Transaksi',
                'Tanggal Pengajuan',
                'Peminjam',
                'Username',
                'Tujuan',
                'Tanggal Mulai',
                'Tanggal Selesai',
                'Status',
                'Fasilitas',
                'Jumlah Item',
                'Catatan',
                'Catatan Admin',
            ]);

            foreach ($rows as $index => $borrowing) {
                fputcsv($handle, [
                    $index + 1,
                    $borrowing->kode_transaksi,
                    optional($borrowing->created_at)->format('d-m-Y H:i'),
                    $borrowing->user?->name ?? '-',
                    $borrowing->user?->username ?? '-',
                    $borrowing->tujuan ?? '-',
                    optional($borrowing->tanggal_mulai)->format('d-m-Y H:i'),
                    optional($borrowing->tanggal_selesai)->format('d-m-Y H:i'),
                    $borrowing->status ?? '-',
                    $this->resourceNames($borrowing),
                    $borrowing->details->count(),
                    $borrowing->catatan ?? '',
                    $borrowing->catatan_admin ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf()
    {
        $rows = $this->reportData();
        $user = auth()->user();

        $periodText = match (true) {
            $this->dateFrom !== '' && $this->dateTo !== '' => Carbon::parse($this->dateFrom)->translatedFormat('d F Y') . ' s/d ' . Carbon::parse($this->dateTo)->translatedFormat('d F Y'),
            $this->dateFrom !== '' => 'Mulai ' . Carbon::parse($this->dateFrom)->translatedFormat('d F Y'),
            $this->dateTo !== '' => 'Sampai ' . Carbon::parse($this->dateTo)->translatedFormat('d F Y'),
            default => 'Seluruh Periode',
        };

        $statusText = $this->status === 'Semua' ? 'Semua Status' : $this->status;

        $signatureImage = null;
        $signatureAttribute = $user?->getAttribute('tanda_tangan');

        if ($signatureAttribute && Storage::disk('public')->exists($signatureAttribute)) {
            $signatureImage = Storage::disk('public')->path($signatureAttribute);
        }

        $html = view('pdf.borrowing-report', [
            'rows' => $rows,
            'periodText' => $periodText,
            'statusText' => $statusText,
            'generatedAt' => now()->translatedFormat('d F Y H:i'),
            'signerName' => $user?->name ?? 'Administrator',
            'signerRole' => $user?->role ?? 'Administrator',
            'signatureImage' => $signatureImage,
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_top' => 12,
            'margin_bottom' => 12,
            'margin_left' => 12,
            'margin_right' => 12,
            'tempDir' => storage_path('app/mpdf'),
        ]);

        $mpdf->SetTitle('Laporan Peminjaman');
        $mpdf->SetAuthor('SARPRAS SMANSA');
        $mpdf->WriteHTML($html);

        $pdf = $mpdf->Output('', 'S');
        $filename = 'laporan-peminjaman-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(
            fn () => print($pdf),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function resetFilter(): void
    {
        $this->reset([
            'search',
            'dateFrom',
            'dateTo',
        ]);

        $this->status = 'Semua';
        $this->resetPage();
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'Menunggu' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
            'Disetujui' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
            'Ditolak' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
            'Dipinjam' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
            'Dikembalikan' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            'Selesai' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
            default => 'bg-gray-100 text-gray-600',
        };
    }
};
?>

<div class="w-full">
    <div class="flex flex-col gap-5 mb-8 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl dark:text-white">Laporan Peminjaman</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cetak dan export laporan transaksi peminjaman berdasarkan periode dan status.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-bold text-emerald-700 bg-emerald-100 rounded-xl hover:bg-emerald-200 disabled:opacity-50">
                <i class="fa-solid fa-file-csv"></i>
                <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                <span wire:loading wire:target="exportCsv">Memproses...</span>
            </button>
            <button type="button" wire:click="exportPdf" wire:loading.attr="disabled" wire:target="exportPdf" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 disabled:opacity-50">
                <i class="fa-solid fa-file-pdf"></i>
                <span wire:loading.remove wire:target="exportPdf">Export PDF</span>
                <span wire:loading wire:target="exportPdf">Membuat PDF...</span>
            </button>
        </div>
    </div>

    <div class="p-4 mb-6 bg-white border border-gray-200 shadow-sm sm:p-6 rounded-2xl sm:rounded-3xl dark:bg-gray-900 dark:border-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-12 md:items-end">
            <div class="md:col-span-4">
                <label class="block mb-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400">Pencarian</label>
                <div class="relative">
                    <i class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 fa-solid fa-magnifying-glass"></i>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Kode, nama, username, tujuan..." class="w-full py-3 pl-10 pr-4 text-sm border rounded-xl bg-slate-50 border-slate-200 dark:bg-gray-800 dark:border-gray-700 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div class="md:col-span-2">
                <label class="block mb-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400">Dari Tanggal</label>
                <input type="date" wire:model.live="dateFrom" class="w-full px-3 py-3 text-sm border rounded-xl bg-slate-50 border-slate-200 dark:bg-gray-800 dark:border-gray-700 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="md:col-span-2">
                <label class="block mb-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400">Sampai Tanggal</label>
                <input type="date" wire:model.live="dateTo" class="w-full px-3 py-3 text-sm border rounded-xl bg-slate-50 border-slate-200 dark:bg-gray-800 dark:border-gray-700 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="md:col-span-2">
                <label class="block mb-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400">Status</label>
                <select wire:model.live="status" class="w-full px-3 py-3 text-sm border rounded-xl bg-slate-50 border-slate-200 dark:bg-gray-800 dark:border-gray-700 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                    @foreach($statusOptions as $statusOption)
                        <option value="{{ $statusOption }}">{{ $statusOption }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <button type="button" wire:click="resetFilter" class="w-full px-4 py-3 text-sm font-bold text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    <i class="mr-1 fa-solid fa-filter-circle-xmark"></i>
                    Reset
                </button>
            </div>
        </div>
    </div>

    <div class="relative overflow-hidden bg-white border border-gray-200 shadow-sm rounded-3xl dark:bg-gray-900 dark:border-gray-800">
        <div wire:loading.flex wire:target="search,dateFrom,dateTo,status" class="absolute inset-0 z-20 items-start justify-center pt-10 bg-white/70 backdrop-blur-sm dark:bg-gray-900/70">
            <div class="flex items-center gap-2 px-4 py-3 text-xs font-bold text-indigo-600 bg-white border border-indigo-100 shadow-lg rounded-xl dark:bg-gray-900 dark:border-indigo-900/50 dark:text-indigo-400">
                <i class="fa-solid fa-spinner animate-spin"></i>
                Memuat laporan...
            </div>
        </div>

        <div class="flex flex-col gap-2 px-5 py-4 border-b border-gray-100 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Data Peminjaman</h2>
                <p class="text-[10px] text-gray-400">Menampilkan {{ $borrowings->total() }} transaksi.</p>
            </div>
            <div class="text-[10px] font-semibold text-gray-400">
                {{ $dateFrom ? Carbon::parse($dateFrom)->format('d M Y') : 'Semua' }}
                -
                {{ $dateTo ? Carbon::parse($dateTo)->format('d M Y') : 'Semua' }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] text-xs text-left">
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 font-bold uppercase text-slate-400">No</th>
                        <th class="px-4 py-3 font-bold uppercase text-slate-400">Kode</th>
                        <th class="px-4 py-3 font-bold uppercase text-slate-400">Peminjam</th>
                        <th class="px-4 py-3 font-bold uppercase text-slate-400">Tujuan</th>
                        <th class="px-4 py-3 font-bold uppercase text-slate-400">Periode</th>
                        <th class="px-4 py-3 font-bold uppercase text-slate-400">Fasilitas</th>
                        <th class="px-4 py-3 font-bold text-center uppercase text-slate-400">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($borrowings as $index => $borrowing)
                        <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-4 text-slate-400">{{ $borrowings->firstItem() + $index }}</td>
                            <td class="px-4 py-4 font-bold text-indigo-600 dark:text-indigo-400">{{ $borrowing->kode_transaksi }}</td>
                            <td class="px-4 py-4">
                                <div class="font-bold text-gray-900 dark:text-white">{{ $borrowing->user?->name ?? '-' }}</div>
                                <div class="text-[10px] text-gray-400">{{ $borrowing->user?->username ?? '-' }}</div>
                            </td>
                            <td class="max-w-[220px] px-4 py-4 text-gray-600 break-words dark:text-gray-300">{{ $borrowing->tujuan ?? '-' }}</td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ optional($borrowing->tanggal_mulai)->format('d M Y H:i') }}</div>
                                <div class="text-[10px] text-gray-400">s/d {{ optional($borrowing->tanggal_selesai)->format('d M Y H:i') }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="max-w-[250px] space-y-1">
                                    @foreach($borrowing->details as $detail)
                                        <div class="flex items-start gap-2">
                                            <span class="mt-0.5 text-indigo-500"><i class="fa-solid {{ $detail->room ? 'fa-door-open' : 'fa-box' }}"></i></span>
                                            <span class="text-gray-600 dark:text-gray-300">
                                                {{ $detail->room?->nama_ruangan ?? $detail->item?->nama_barang ?? '-' }}
                                                <span class="text-[10px] text-gray-400">×{{ $detail->jumlah }}</span>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold {{ $this->statusClass($borrowing->status) }}">
                                    {{ $borrowing->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center text-gray-400">
                                <i class="mb-3 text-3xl fa-solid fa-folder-open"></i>
                                <div class="text-sm font-semibold">Tidak ada data peminjaman.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-800">
            {{ $borrowings->links() }}
        </div>
    </div>

    <x-toast />
</div>
