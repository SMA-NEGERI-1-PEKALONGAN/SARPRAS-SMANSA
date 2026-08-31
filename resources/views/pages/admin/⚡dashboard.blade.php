<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Carbon\Carbon;
use App\Models\Borrowing;
use App\Models\BorrowingDetail;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] #[Title('Dashboard Admin')] class extends Component
{
    public string $topTab = 'items';

    public function with(): array
    {
        $today = now()->startOfDay();
        $tomorrow = now()->copy()->addDay()->startOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $pendingCount = Borrowing::where('status', 'Menunggu')->count();

        $ongoingCount = Borrowing::whereIn('status', ['Disetujui', 'Dipinjam'])
            ->where('tanggal_mulai', '<', $tomorrow)
            ->where('tanggal_selesai', '>', $today)
            ->count();

        $completedToday = Borrowing::whereIn('status', ['Selesai', 'Dikembalikan'])
            ->where(function ($q) use ($today, $tomorrow) {
                $q->whereBetween('tanggal_selesai', [$today, $tomorrow])
                    ->orWhereBetween('updated_at', [$today, $tomorrow]);
            })
            ->count();

        $monthTotal = Borrowing::whereBetween('created_at', [$monthStart, $monthEnd])->count();

        $pendingRequests = Borrowing::with(['user', 'details.room', 'details.item'])
            ->where('status', 'Menunggu')
            ->orderBy('tanggal_mulai')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $topItems = BorrowingDetail::query()
            ->select('item_id', DB::raw('SUM(jumlah) as total_dipinjam'))
            ->whereNotNull('item_id')
            ->whereHas('borrowing', function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('tanggal_mulai', [$monthStart, $monthEnd])
                    ->whereNotIn('status', ['Ditolak']);
            })
            ->with('item:id,nama_barang,kode_barang')
            ->groupBy('item_id')
            ->orderByDesc('total_dipinjam')
            ->limit(5)
            ->get();

        $topRooms = BorrowingDetail::query()
            ->select('room_id', DB::raw('COUNT(*) as total_dipinjam'))
            ->whereNotNull('room_id')
            ->whereHas('borrowing', function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('tanggal_mulai', [$monthStart, $monthEnd])
                    ->whereNotIn('status', ['Ditolak']);
            })
            ->with('room:id,nama_ruangan,kode_ruangan')
            ->groupBy('room_id')
            ->orderByDesc('total_dipinjam')
            ->limit(5)
            ->get();

        $topItemMax = (int) ($topItems->max('total_dipinjam') ?: 1);
        $topRoomMax = (int) ($topRooms->max('total_dipinjam') ?: 1);

        $recentActivities = Borrowing::with(['user', 'details.room', 'details.item'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        // Grafik tren peminjaman 6 bulan terakhir: Ruangan vs Barang.
        $chartMonths = collect(range(5, 0))->map(function ($monthsAgo) {
            $date = now()->copy()->subMonths($monthsAgo);
            return [
                'key' => $date->format('Y-m'),
                'label' => $date->translatedFormat('M'),
            ];
        })->push([
            'key' => now()->format('Y-m'),
            'label' => now()->translatedFormat('M'),
        ]);

        $chartRoomCounts = [];
        $chartItemCounts = [];

        foreach ($chartMonths as $month) {
            $monthStart = Carbon::createFromFormat('Y-m', $month['key'])->startOfMonth();
            $monthEnd = Carbon::createFromFormat('Y-m', $month['key'])->endOfMonth();

            $chartRoomCounts[] = BorrowingDetail::whereNotNull('room_id')
                ->whereHas('borrowing', function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('tanggal_mulai', [$monthStart, $monthEnd])
                        ->whereNotIn('status', ['Ditolak']);
                })
                ->count();

            $chartItemCounts[] = BorrowingDetail::whereNotNull('item_id')
                ->whereHas('borrowing', function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('tanggal_mulai', [$monthStart, $monthEnd])
                        ->whereNotIn('status', ['Ditolak']);
                })
                ->count();
        }

        return compact(
            'pendingCount',
            'ongoingCount',
            'completedToday',
            'monthTotal',
            'pendingRequests',
            'topItems',
            'topRooms',
            'topItemMax',
            'topRoomMax',
            'recentActivities',
            'chartMonths',
            'chartRoomCounts',
            'chartItemCounts'
        );
    }

    public function openApproval(int $id): void
    {
        $this->dispatch('open-approval', borrowingId: $id);
    }

    public function topResourceName($detail): string
    {
        return $detail->item?->nama_barang
            ?? $detail->room?->nama_ruangan
            ?? 'Fasilitas';
    }

    public function topResourceCode($detail): string
    {
        return $detail->item?->kode_barang
            ?? $detail->room?->kode_ruangan
            ?? '-';
    }

    public function activityResourceNames($borrowing): string
    {
        return $borrowing->details
            ->map(fn ($detail) => $this->topResourceName($detail))
            ->filter()
            ->unique()
            ->take(2)
            ->implode(', ');
    }

    public function activityIcon($status): string
    {
        return match ($status) {
            'Menunggu' => 'fa-clock',
            'Disetujui' => 'fa-circle-check',
            'Dipinjam' => 'fa-box-open',
            'Dikembalikan', 'Selesai' => 'fa-rotate-left',
            'Ditolak' => 'fa-circle-xmark',
            default => 'fa-bell',
        };
    }

    public function statusClass($status): string
    {
        return match ($status) {
            'Menunggu' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
            'Disetujui' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
            'Dipinjam' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
            'Dikembalikan', 'Selesai' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            'Ditolak' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
            default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        };
    }
};
?>

<div
    class=""
    wire:poll.30s
>
    <style>
        .dashboard-no-scrollbar::-webkit-scrollbar{display:none}
        .dashboard-no-scrollbar{-ms-overflow-style:none;scrollbar-width:none}
    </style>

    {{-- Header --}}
    <div class="flex flex-col gap-3 mb-6 sm:flex-row sm:items-end sm:justify-between sm:mb-8">
        <div>
            
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl text-slate-900 dark:text-white">
                Dashboard Admin
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Pantau pengajuan, peminjaman aktif, dan aktivitas sistem secara realtime.
            </p>
        </div>

        <div class="text-xs text-slate-400 dark:text-slate-500">
            {{ now()->translatedFormat('l, d F Y') }}
        </div>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 xl:grid-cols-4 sm:mb-8">
        <div class="group rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-amber-900/50 dark:from-amber-900/20 dark:to-slate-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold tracking-wide uppercase text-amber-700/80 dark:text-amber-300">Menunggu Proses</p>
                    <p class="mt-2 text-3xl font-bold text-amber-700 dark:text-amber-300">{{ number_format($pendingCount) }}</p>
                    <p class="mt-1 text-[11px] text-amber-700/70 dark:text-amber-300/70">Pengajuan perlu ditindaklanjuti</p>
                </div>
                <div class="flex items-center justify-center h-11 w-11 shrink-0 rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300">
                    <i class="text-lg fa-solid fa-hourglass-half"></i>
                </div>
            </div>
        </div>

        <div class="group rounded-2xl border border-blue-200/80 bg-gradient-to-br from-blue-50 to-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-blue-900/50 dark:from-blue-900/20 dark:to-slate-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold tracking-wide uppercase text-blue-700/80 dark:text-blue-300">Sedang Dipinjam</p>
                    <p class="mt-2 text-3xl font-bold text-blue-700 dark:text-blue-300">{{ number_format($ongoingCount) }}</p>
                    <p class="mt-1 text-[11px] text-blue-700/70 dark:text-blue-300/70">Peminjaman sedang berlangsung</p>
                </div>
                <div class="flex items-center justify-center text-blue-600 bg-blue-100 h-11 w-11 shrink-0 rounded-xl dark:bg-blue-900/40 dark:text-blue-300">
                    <i class="text-lg fa-solid fa-box-open"></i>
                </div>
            </div>
        </div>

        <div class="group rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50 to-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-emerald-900/50 dark:from-emerald-900/20 dark:to-slate-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold tracking-wide uppercase text-emerald-700/80 dark:text-emerald-300">Selesai Hari Ini</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-700 dark:text-emerald-300">{{ number_format($completedToday) }}</p>
                    <p class="mt-1 text-[11px] text-emerald-700/70 dark:text-emerald-300/70">Dikembalikan / selesai</p>
                </div>
                <div class="flex items-center justify-center h-11 w-11 shrink-0 rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300">
                    <i class="text-lg fa-solid fa-circle-check"></i>
                </div>
            </div>
        </div>

        <div class="group rounded-2xl border border-violet-200/80 bg-gradient-to-br from-violet-50 to-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-violet-900/50 dark:from-violet-900/20 dark:to-slate-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold tracking-wide uppercase text-violet-700/80 dark:text-violet-300">Total Bulan Ini</p>
                    <p class="mt-2 text-3xl font-bold text-violet-700 dark:text-violet-300">{{ number_format($monthTotal) }}</p>
                    <p class="mt-1 text-[11px] text-violet-700/70 dark:text-violet-300/70">Seluruh transaksi</p>
                </div>
                <div class="flex items-center justify-center h-11 w-11 shrink-0 rounded-xl bg-violet-100 text-violet-600 dark:bg-violet-900/40 dark:text-violet-300">
                    <i class="text-lg fa-solid fa-chart-column"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Pending + Activities --}}
    <div class="grid grid-cols-1 gap-6 mb-6 xl:grid-cols-12">
        <section class="bg-white border shadow-sm xl:col-span-8 rounded-2xl border-slate-200 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Menunggu Proses</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">5 pengajuan terbaru yang membutuhkan tindakan.</p>
                </div>
                <a href="{{ route('booking') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                    Lihat Semua
                </a>
            </div>

            <div class="overflow-x-auto dashboard-no-scrollbar">
                <table class="min-w-[760px] w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr class="text-left text-[11px] font-bold uppercase tracking-wide text-slate-400">
                            <th class="px-5 py-3">Peminjam</th>
                            <th class="px-5 py-3">Item / Ruang</th>
                            <th class="px-5 py-3">Waktu</th>
                            <th class="px-5 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($pendingRequests as $request)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $request->user?->name ?? '-' }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $request->kode_transaksi }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="space-y-1">
                                        @foreach($request->details->take(2) as $detail)
                                            <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                                                {{ $this->topResourceName($detail) }}
                                                <span class="text-slate-400">#{{ $this->topResourceCode($detail) }}</span>
                                            </div>
                                        @endforeach
                                        @if($request->details->count() > 2)
                                            <div class="text-[11px] text-indigo-500">+{{ $request->details->count() - 2 }} lainnya</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="text-xs font-semibold text-slate-800 dark:text-white">
                                        {{ optional($request->tanggal_mulai)->translatedFormat('d M Y') }}
                                    </div>
                                    <div class="text-[11px] text-slate-400">
                                        {{ optional($request->tanggal_mulai)->format('H:i') }}–{{ optional($request->tanggal_selesai)->format('H:i') }}
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <button
                                        type="button"
                                        wire:click="openApproval({{ $request->id }})"
                                        class="inline-flex items-center gap-2 px-3 py-2 text-xs font-bold text-white transition bg-indigo-600 rounded-xl hover:bg-indigo-700"
                                    >
                                        <i class="fa-solid fa-gavel"></i>
                                        Proses
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-sm text-center text-slate-400">
                                    Tidak ada pengajuan yang menunggu proses.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bg-white border shadow-sm xl:col-span-4 rounded-2xl border-slate-200 dark:border-slate-800 dark:bg-slate-900">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Aktivitas Terkini</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Perubahan status dan aktivitas peminjaman terbaru.</p>
            </div>

            <div class="max-h-[420px] overflow-y-auto dashboard-no-scrollbar">
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($recentActivities as $activity)
                        <div class="flex gap-3 px-5 py-4">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $this->statusClass($activity->status) }}">
                                <i class="fa-solid {{ $this->activityIcon($activity->status) }} text-xs"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs leading-relaxed text-slate-700 dark:text-slate-300">
                                    <span class="font-bold text-slate-900 dark:text-white">{{ $activity->user?->name ?? 'Pengguna' }}</span>
                                    · {{ $this->activityResourceNames($activity) ?: 'Peminjaman fasilitas' }}
                                </p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold {{ $this->statusClass($activity->status) }}">
                                        {{ $activity->status }}
                                    </span>
                                    <span class="text-[10px] text-slate-400">{{ optional($activity->updated_at)->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-12 text-sm text-center text-slate-400">Belum ada aktivitas.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    {{-- Grafik Tren Peminjaman --}}
    <section class="mb-6 bg-white border shadow-sm rounded-2xl border-slate-200 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 px-5 py-4 border-b border-slate-100 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Grafik Peminjaman</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Tren peminjaman barang dan ruangan selama 6 bulan terakhir.</p>
            </div>

            <div class="flex items-center gap-4 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                <span class="inline-flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                    Barang
                </span>
                <span class="inline-flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                    Ruangan
                </span>
            </div>
        </div>

        <div class="p-5 sm:p-6">
            @php
                $chartMax = max(
                    1,
                    max($chartRoomCounts ?: [0]),
                    max($chartItemCounts ?: [0])
                );
            @endphp

            <div class="overflow-x-auto dashboard-no-scrollbar">
                <div class="min-w-[680px]">
                    <div class="grid items-end h-64 grid-cols-6 gap-3">
                        @foreach($chartMonths as $index => $month)
                            @php
                                $itemValue = $chartItemCounts[$index] ?? 0;
                                $roomValue = $chartRoomCounts[$index] ?? 0;
                                $itemHeight = $chartMax > 0 ? max(4, ($itemValue / $chartMax) * 100) : 4;
                                $roomHeight = $chartMax > 0 ? max(4, ($roomValue / $chartMax) * 100) : 4;
                            @endphp

                            <div class="flex flex-col justify-end h-full">
                                <div class="flex h-full items-end justify-center gap-1.5">
                                    <div class="relative flex items-end justify-center w-5 h-full group">
                                        <div
                                            class="w-full transition-all duration-500 rounded-t-lg bg-indigo-500/90 hover:bg-indigo-600"
                                            style="height: {{ $itemHeight }}%;"
                                            title="Barang: {{ $itemValue }}"
                                        ></div>
                                        <div class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-slate-900 px-2 py-1 text-[9px] font-semibold text-white opacity-0 shadow-lg transition group-hover:opacity-100">
                                            {{ $itemValue }} barang
                                        </div>
                                    </div>

                                    <div class="relative flex items-end justify-center w-5 h-full group">
                                        <div
                                            class="w-full transition-all duration-500 rounded-t-lg bg-emerald-500/90 hover:bg-emerald-600"
                                            style="height: {{ $roomHeight }}%;"
                                            title="Ruangan: {{ $roomValue }}"
                                        ></div>
                                        <div class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-slate-900 px-2 py-1 text-[9px] font-semibold text-white opacity-0 shadow-lg transition group-hover:opacity-100">
                                            {{ $roomValue }} ruangan
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 text-center text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                    {{ $month['label'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-4 mt-4 border-t border-slate-100 dark:border-slate-800">
                <span class="text-[10px] text-slate-400">Sumber data: transaksi aktif/selesai, tidak termasuk Ditolak.</span>
                <span class="text-[10px] font-semibold text-slate-400">Hover batang untuk melihat jumlah.</span>
            </div>
        </div>
    </section>

    {{-- Top Resources --}}
    <section class="bg-white border shadow-sm rounded-2xl border-slate-200 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 px-5 py-4 border-b border-slate-100 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Top 5 Barang & Ruangan Paling Sering Dipinjam</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Peringkat berdasarkan transaksi bulan berjalan.</p>
            </div>

            <div class="flex w-full p-1 sm:w-auto rounded-xl bg-slate-100 dark:bg-slate-800">
                <button
                    type="button"
                    wire:click="$set('topTab', 'items')"
                    class="flex-1 sm:flex-none rounded-lg px-4 py-2 text-xs font-bold transition {{ $topTab === 'items' ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-900 dark:text-indigo-400' : 'text-slate-500 dark:text-slate-400' }}"
                >
                    Barang
                </button>
                <button
                    type="button"
                    wire:click="$set('topTab', 'rooms')"
                    class="flex-1 sm:flex-none rounded-lg px-4 py-2 text-xs font-bold transition {{ $topTab === 'rooms' ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-900 dark:text-indigo-400' : 'text-slate-500 dark:text-slate-400' }}"
                >
                    Ruangan
                </button>
            </div>
        </div>

        <div class="p-5 sm:p-6">
            @if($topTab === 'items')
                <div class="space-y-4">
                    @forelse($topItems as $index => $row)
                        <div>
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <div class="flex items-center min-w-0 gap-3">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-[10px] font-bold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">
                                        {{ $index + 1 }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-xs font-bold truncate text-slate-800 dark:text-white">
                                            {{ $row->item?->nama_barang ?? '-' }}
                                        </div>
                                        <div class="text-[10px] text-slate-400">#{{ $row->item?->kode_barang ?? '-' }}</div>
                                    </div>
                                </div>
                                <span class="text-xs font-bold text-indigo-600 shrink-0 dark:text-indigo-400">
                                    {{ number_format($row->total_dipinjam) }}x
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    class="h-full transition-all duration-500 bg-indigo-500 rounded-full"
                                    style="width: {{ min(100, (($row->total_dipinjam / $topItemMax) * 100)) }}%"
                                ></div>
                            </div>
                        </div>
                    @empty
                        <div class="py-10 text-sm text-center text-slate-400">Belum ada data peminjaman barang bulan ini.</div>
                    @endforelse
                </div>
            @else
                <div class="space-y-4">
                    @forelse($topRooms as $index => $row)
                        <div>
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <div class="flex items-center min-w-0 gap-3">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-[10px] font-bold text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        {{ $index + 1 }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-xs font-bold truncate text-slate-800 dark:text-white">
                                            {{ $row->room?->nama_ruangan ?? '-' }}
                                        </div>
                                        <div class="text-[10px] text-slate-400">#{{ $row->room?->kode_ruangan ?? '-' }}</div>
                                    </div>
                                </div>
                                <span class="text-xs font-bold shrink-0 text-emerald-600 dark:text-emerald-400">
                                    {{ number_format($row->total_dipinjam) }}x
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    class="h-full transition-all duration-500 rounded-full bg-emerald-500"
                                    style="width: {{ min(100, (($row->total_dipinjam / $topRoomMax) * 100)) }}%"
                                ></div>
                            </div>
                        </div>
                    @empty
                        <div class="py-10 text-sm text-center text-slate-400">Belum ada data peminjaman ruangan bulan ini.</div>
                    @endforelse
                </div>
            @endif
        </div>
    </section>

    {{-- Approval bridge: kompatibel dengan modal approval yang sudah ada --}}
    <div
        x-data
        x-on:open-approval.window="
            const id = $event.detail.borrowingId;
            if (id) {
                $wire.dispatch('open-existing-approval', { borrowingId: id });
            }
        "
    ></div>
</div>
