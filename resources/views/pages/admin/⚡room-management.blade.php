<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Room;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

new #[Layout('layouts.app')] #[Title('Manajemen Ruangan')] class extends Component 
{
    use WithPagination, WithFileUploads;

    // State Datatable & Filter
    public $search = '';
    public $filterTipe = '';
    public $view = 10;
    public $sortColumn = 'kode_ruangan';
    public $sortDirection = 'asc';

    // State Modal Form CRUD
    public $isModalOpen = false;
    public ?Room $editingRoom = null;

    // Fields Form CRUD
    public $kode_ruangan = '';
    public $nama_ruangan = '';
    public $tipe = '';
    public $kapasitas = '';
    public $fasilitas = '';
    public $status_tersedia = true;
    public $icon = 'fa-solid fa-door-open';

    
    // State Modal & Field Import
    public $isImportModalOpen = false;
    public $importFile;

    // TAMBAHKAN 3 VARIABEL INI
    public $isImportFinished = false;
    public $successList = [];
    public $failedList = [];

    // Reset halaman saat filter berubah
    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterTipe() { $this->resetPage(); }
    public function updatingView() { $this->resetPage(); }

    public function sort($column)
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function with(): array
    {
        $query = Room::query();

        if ($this->search) {
            $query->where(function($q) {
                $q->where('kode_ruangan', 'like', '%' . $this->search . '%')
                  ->orWhere('nama_ruangan', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filterTipe) {
            $query->where('tipe', $this->filterTipe);
        }

        return [
            'rooms' => $query->orderBy($this->sortColumn, $this->sortDirection)->paginate($this->view),
        ];
    }

    // ==========================================
    // FUNGSI CRUD STANDAR
    // ==========================================
    public function create()
    {
        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function edit(Room $room)
    {
        $this->resetForm();
        $this->editingRoom = $room;
        
        $this->kode_ruangan = $room->kode_ruangan;
        $this->nama_ruangan = $room->nama_ruangan;
        $this->tipe = $room->tipe;
        $this->kapasitas = $room->kapasitas;
        $this->fasilitas = $room->fasilitas;
        $this->status_tersedia = $room->status_tersedia;
        $this->icon = $room->icon ?? 'fa-solid fa-door-open';
        
        $this->isModalOpen = true;
    }

    public function save()
    {
        $this->validate([
            'kode_ruangan' => ['required', 'string', 'max:50', Rule::unique('rooms')->ignore($this->editingRoom?->id)],
            'nama_ruangan' => 'required|string|max:255',
            'tipe' => 'required|in:Laboratorium,Aula,Ruang Rapat,Fasilitas Olahraga,Lainnya',
            'kapasitas' => 'nullable|integer|min:1',
            'fasilitas' => 'nullable|string',
            'status_tersedia' => 'boolean',
            'icon' => 'nullable|string|max:100',
        ]);

        $data = [
            'kode_ruangan' => strtoupper($this->kode_ruangan),
            'nama_ruangan' => $this->nama_ruangan,
            'tipe' => $this->tipe,
            'kapasitas' => $this->kapasitas,
            'fasilitas' => $this->fasilitas,
            'status_tersedia' => (bool) $this->status_tersedia,
            'icon' => $this->icon ?: 'fa-solid fa-door-open',
        ];

        if ($this->editingRoom) {
            $this->editingRoom->update($data);
            $msg = 'Data ruangan berhasil diperbarui!';
        } else {
            Room::create($data);
            $msg = 'Ruangan baru berhasil ditambahkan!';
        }

        $this->isModalOpen = false;
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    public function toggleStatus(Room $room)
    {
        $room->update(['status_tersedia' => !$room->status_tersedia]);
        $statusText = $room->status_tersedia ? 'Tersedia' : 'Sedang Tidak Tersedia';
        $this->dispatch('toast', type: 'success', message: "Status ruangan diubah menjadi $statusText!");
    }

    public function resetForm()
    {
        $this->reset(['kode_ruangan', 'nama_ruangan', 'tipe', 'kapasitas', 'fasilitas', 'editingRoom']);
        $this->status_tersedia = true;
        $this->icon = 'fa-solid fa-door-open';
        $this->resetValidation();
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
    }

    // ==========================================
    // FUNGSI IMPORT CSV
    // ==========================================
    public function openImportModal()
    {
        // Reset seluruh state import saat dibuka
        $this->reset(['importFile', 'isImportFinished', 'successList', 'failedList']);
        $this->resetValidation('importFile');
        $this->isImportModalOpen = true;
    }

    public function closeImportModal()
    {
        $this->isImportModalOpen = false;
        $this->reset(['importFile', 'isImportFinished', 'successList', 'failedList']);
    }

    public function importData()
    {
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt|max:2048',
        ], [
            'importFile.required' => 'Silakan pilih file CSV terlebih dahulu.',
            'importFile.mimes' => 'Format file harus berupa CSV.',
        ]);

        $this->successList = [];
        $this->failedList = [];

        try {
            $path = $this->importFile->getRealPath();
            $content = file_get_contents($path);
            $delimiter = strpos($content, ';') !== false ? ';' : ',';
            $handle = fopen($path, "r");

            // Lewati BOM jika ada
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle); 
            }

            $header = fgetcsv($handle, 1000, $delimiter); 
            $allowedTipe = ['Laboratorium', 'Aula', 'Ruang Rapat', 'Fasilitas Olahraga', 'Lainnya'];
            $rowNum = 1; // Menghitung baris (1 = header)

            // Array penampung sementara untuk deteksi duplikat di dalam file CSV yang sama
            $processedCodes = [];

            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                $rowNum++;
                
                // Pastikan minimal ada kolom kode, nama, tipe
                if (!isset($row[0], $row[1], $row[2]) || empty(trim($row[0]))) {
                    $this->failedList[] = [
                        'row' => $rowNum,
                        'kode' => $row[0] ?? '-',
                        'reason' => 'Format tidak lengkap / Kode kosong'
                    ];
                    continue;
                }

                $kode = strtoupper(trim($row[0]));
                $nama = trim($row[1]);
                $tipeInput = trim($row[2]);

                // 1. Cek duplikasi di dalam file CSV yang sama
                if (in_array($kode, $processedCodes)) {
                    $this->failedList[] = [
                        'row' => $rowNum,
                        'kode' => $kode,
                        'reason' => 'Kode ruangan duplikat dalam file CSV'
                    ];
                    continue;
                }

                // 2. Cek duplikasi di database
                if (Room::where('kode_ruangan', $kode)->exists()) {
                    $this->failedList[] = [
                        'row' => $rowNum,
                        'kode' => $kode,
                        'reason' => 'Kode ruangan sudah terdaftar di sistem'
                    ];
                    continue;
                }

                // Pengecekan tipe
                $tipe = 'Lainnya';
                foreach ($allowedTipe as $allowed) {
                    if (strcasecmp($tipeInput, $allowed) == 0) {
                        $tipe = $allowed;
                        break;
                    }
                }

                $kapasitas = isset($row[3]) && is_numeric(trim($row[3])) ? (int)trim($row[3]) : null;
                $fasilitas = isset($row[4]) ? trim($row[4]) : null;
                
                $statusInput = isset($row[5]) ? strtolower(trim($row[5])) : '1';
                $status_tersedia = in_array($statusInput, ['1', 'true', 'ya', 'yes', 'tersedia']) ? true : false;
                
                $icon = !empty($row[6]) ? trim($row[6]) : 'fa-solid fa-door-open';

                try {
                    // Menggunakan Create agar tidak menimpa data yang ada
                    Room::create([
                        'kode_ruangan' => $kode,
                        'nama_ruangan' => $nama,
                        'tipe' => $tipe,
                        'kapasitas' => $kapasitas,
                        'fasilitas' => $fasilitas,
                        'status_tersedia' => $status_tersedia,
                        'icon' => $icon,
                    ]);

                    // Simpan kode yang sukses ke penampung
                    $processedCodes[] = $kode;
                    $this->successList[] = "Baris $rowNum: $nama ($kode)";

                } catch (\Exception $e) {
                    $this->failedList[] = [
                        'row' => $rowNum,
                        'kode' => $kode,
                        'reason' => 'Gagal simpan database'
                    ];
                }
            }
            fclose($handle);

            // Ubah state jadi selesai
            $this->isImportFinished = true;
            $this->dispatch('toast', type: 'info', message: "Proses import selesai diproses!");

        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: "Terjadi kesalahan: " . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- Header & Info Halaman --}}
    <div class="flex flex-col gap-2 mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Manajemen Ruangan</h1>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Kelola daftar ruangan, kapasitas, fasilitas, dan
            status ketersediaan peminjaman.</p>
    </div>

    {{-- Container Utama --}}
    <div
        class="overflow-hidden transition-all bg-white border border-gray-200 shadow-sm rounded-3xl dark:bg-gray-900 dark:border-gray-800">

        {{-- Top Actions --}}
        <div
            class="flex flex-col items-center justify-between gap-4 p-4 border-b border-gray-100 md:flex-row dark:border-gray-800">
            <div>
                <x-button wire:click="openImportModal" variant="success" class="flex items-center gap-2">
                    <i class="fa-solid fa-file-import"></i> Import CSV
                </x-button>
            </div>
            <x-button wire:click="create" variant="primary"
                class="flex items-center gap-2 shadow-md shadow-indigo-500/20">
                <i class="fa-solid fa-plus"></i> Tambah Ruangan
            </x-button>
        </div>

        {{-- Filters & Search --}}
        <div
            class="flex flex-col items-center justify-between gap-4 p-6 border-b border-gray-100 md:flex-row dark:border-gray-800">
            <div class="flex flex-wrap items-center w-full gap-4 md:w-auto">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-gray-400 uppercase">Tampil</span>
                    <select wire:model.live="view"
                        class="px-3 py-2 text-xs font-bold border-none outline-none bg-gray-50 dark:bg-gray-800 rounded-xl focus:ring-indigo-500 dark:text-white">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-gray-400 uppercase">Filter Tipe</span>
                    <select wire:model.live="filterTipe"
                        class="px-3 py-2 text-xs font-bold border-none outline-none bg-gray-50 dark:bg-gray-800 rounded-xl focus:ring-indigo-500 dark:text-white">
                        <option value="">Semua Tipe</option>
                        <option value="Laboratorium">Laboratorium</option>
                        <option value="Aula">Aula</option>
                        <option value="Ruang Rapat">Ruang Rapat</option>
                        <option value="Fasilitas Olahraga">Fasilitas Olahraga</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </div>
            </div>

            <div class="relative w-full md:w-80">
                <i class="absolute text-gray-400 -translate-y-1/2 fa-solid fa-magnifying-glass left-4 top-1/2"></i>
                <input type="text" wire:model.live.debounce.300ms="search"
                    class="w-full bg-gray-50 dark:bg-gray-800 border-none rounded-2xl pl-11 pr-4 py-2.5 text-xs font-bold focus:ring-2 focus:ring-indigo-500 dark:text-white transition-all outline-none"
                    placeholder="Cari kode atau nama ruangan...">
            </div>
        </div>

        {{-- Tabel Data --}}
        <div class="relative overflow-x-auto font-mono text-sm min-h-[300px]">
            {{-- Loading overlay --}}
            <div wire:loading.flex wire:target="search, filterTipe, view, sort, toggleStatus, resetPage, importData"
                class="absolute inset-0 bg-white/50 dark:bg-gray-900/50 backdrop-blur-[2px] z-10 items-center justify-center transition-all">
                <div class="flex flex-col items-center gap-2">
                    <i class="text-4xl text-indigo-600 fa-solid fa-circle-notch fa-spin"></i>
                    <span class="text-[10px] font-bold tracking-widest text-indigo-600 uppercase">Memuat...</span>
                </div>
            </div>

            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-800">
                        <th class="w-12 px-6 py-4 text-center text-gray-400">#</th>
                        <th class="px-6 py-4 font-bold text-gray-400 uppercase cursor-pointer hover:text-indigo-600"
                            wire:click="sort('nama_ruangan')">
                            Ruangan {!! $sortColumn == 'nama_ruangan' ? ($sortDirection == 'asc' ? '↑' : '↓') : '' !!}
                        </th>
                        <th class="px-6 py-4 font-bold text-gray-400 uppercase cursor-pointer hover:text-indigo-600"
                            wire:click="sort('tipe')">
                            Tipe & Kapasitas {!! $sortColumn == 'tipe' ? ($sortDirection == 'asc' ? '↑' : '↓') : '' !!}
                        </th>
                        <th class="px-6 py-4 font-bold text-gray-400 uppercase">Fasilitas</th>
                        <th class="px-6 py-4 font-bold text-gray-400 uppercase">Status</th>
                        <th class="px-6 py-4 font-bold text-center text-gray-400 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($rooms as $index => $room)
                    <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/40">
                        <td class="px-6 py-4 text-center text-gray-500">{{ $rooms->firstItem() + $index }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex items-center justify-center w-10 h-10 text-indigo-600 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 dark:text-indigo-400">
                                    <i class="text-lg {{ $room->icon ?: 'fa-solid fa-door-open' }}"></i>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900 dark:text-white">{{ $room->nama_ruangan }}</div>
                                    <div class="text-xs font-bold text-indigo-600">{{ $room->kode_ruangan }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900 dark:text-white">{{ $room->tipe }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $room->kapasitas ? $room->kapasitas . ' Orang' : 'Kapasitas Tidak Ditetapkan' }}
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-xs text-gray-600 dark:text-gray-400 line-clamp-2 max-w-[200px]"
                                title="{{ $room->fasilitas }}">
                                {{ $room->fasilitas ?: '-' }}
                            </p>
                        </td>
                        <td class="px-6 py-4">
                            <button wire:click="toggleStatus({{ $room->id }})" wire:loading.attr="disabled"
                                class="px-3 py-1 rounded-full text-[10px] font-bold uppercase transition-all {{ $room->status_tersedia ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-red-100 text-red-700 hover:bg-red-200' }}">
                                {{ $room->status_tersedia ? 'Tersedia' : 'Tdk Tersedia' }}
                            </button>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="edit({{ $room->id }})" title="Edit Data"
                                    class="p-2 text-blue-600 transition-colors bg-blue-100 rounded-lg hover:bg-blue-200">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 font-medium text-center text-gray-400">Tidak ada data ruangan
                            yang ditemukan.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="p-6 border-t border-gray-100 dark:border-gray-800">
            {{ $rooms->links() }}
        </div>
    </div>

    {{-- MODAL TAMBAH/EDIT --}}
    <section x-data="{ open: @entangle('isModalOpen') }">
        <template x-teleport="body">
            <div x-show="open" class="fixed inset-0 z-9999 flex items-center justify-center p-4" x-cloak>
                <div x-show="open" x-transition.opacity wire:click="closeModal"
                    class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>

                <div x-show="open" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    class="relative w-full max-w-2xl overflow-y-auto bg-white shadow-2xl dark:bg-gray-900 rounded-3xl p-8 max-h-[90vh]">

                    <div class="flex items-center justify-between mb-6">
                        <h4 class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ $editingRoom ? 'Edit Ruangan' : 'Tambah Ruangan Baru' }}</h4>
                        <button wire:click="closeModal"
                            class="text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-white">
                            <i class="text-xl fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <form wire:submit="save" class="space-y-4">
                        {{-- Row 1: Kode & Nama --}}
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <x-input wire:model="kode_ruangan" label="Kode Ruangan" name="kode_ruangan" type="text"
                                    placeholder="Cth: LAB-KOM-1" required />
                                <span class="block mt-1 text-[10px] text-gray-400">Kode harus unik & tanpa spasi lebih
                                    baik.</span>
                            </div>
                            <x-input wire:model="nama_ruangan" label="Nama Ruangan" name="nama_ruangan" type="text"
                                placeholder="Cth: Laboratorium Komputer Utama" required />
                        </div>

                        {{-- Row 2: Tipe & Kapasitas --}}
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="block mb-1 text-sm font-bold text-gray-700 dark:text-gray-300">Tipe
                                    Ruangan</label>
                                <select wire:model="tipe"
                                    class="w-full px-4 py-2.5 text-sm bg-gray-50 dark:bg-gray-800 border-none rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none dark:text-white">
                                    <option value="">-- Pilih Tipe --</option>
                                    <option value="Laboratorium">Laboratorium</option>
                                    <option value="Aula">Aula</option>
                                    <option value="Ruang Rapat">Ruang Rapat</option>
                                    <option value="Fasilitas Olahraga">Fasilitas Olahraga</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                                @error('tipe') <span class="mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>

                            <x-input wire:model="kapasitas" label="Kapasitas (Orang)" name="kapasitas" type="number"
                                placeholder="Cth: 40" min="0" class="md:col-span-2" />
                        </div>

                        {{-- Icon FontAwesome dengan Preview --}}
                        <div>
                            <label class="block mb-1 text-sm font-bold text-gray-700 dark:text-gray-300">Icon Ruangan
                                (FontAwesome)</label>
                            <div class="flex items-center gap-3">
                                {{-- Kotak Preview Ikon --}}
                                <div
                                    class="flex items-center justify-center flex-shrink-0 w-12 text-indigo-600 h-11 bg-indigo-50 dark:bg-indigo-900/30 dark:text-indigo-400 rounded-xl">
                                    <i class="text-xl {{ $icon ?: 'fa-solid fa-door-open' }}"></i>
                                </div>
                                {{-- Input Teks Ikon --}}
                                <div class="flex-1">
                                    <input type="text" wire:model.live.debounce.300ms="icon"
                                        class="w-full px-4 py-2.5 text-sm transition-all border-none outline-none bg-gray-50 dark:bg-gray-800 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white"
                                        placeholder="Contoh: fa-solid fa-computer">
                                </div>
                            </div>
                            <span class="block mt-1 text-[10px] text-gray-400">Ketik class FontAwesome (cth:
                                <code>fa-solid fa-desktop</code>, <code>fa-solid fa-flask</code>). Preview ikon akan
                                otomatis berubah.</span>
                        </div>

                        {{-- Fasilitas --}}
                        <div>
                            <label class="block mb-1 text-sm font-bold text-gray-700 dark:text-gray-300">Fasilitas
                                Ruangan (Pisahkan dengan koma)</label>
                            <textarea wire:model="fasilitas" rows="3"
                                class="w-full px-4 py-3 text-sm transition-all border-none outline-none bg-gray-50 dark:bg-gray-800 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white"
                                placeholder="Contoh: 40 PC, AC, Proyektor, Papan Tulis..."></textarea>
                        </div>

                        {{-- Status --}}
                        <div>
                            <label class="block mb-1 text-sm font-bold text-gray-700 dark:text-gray-300">Status
                                Ketersediaan</label>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" wire:model="status_tersedia"
                                    class="w-5 h-5 text-indigo-600 transition-all border-gray-300 rounded focus:ring-indigo-500 bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Tersedia untuk
                                    dipinjam (Hapus centang jika ruangan sedang renovasi/rusak)</span>
                            </label>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex justify-end gap-3 pt-4 mt-6 border-t border-gray-100 dark:border-gray-800">
                            <x-button type="button" wire:click="closeModal" variant="secondary"
                                class="text-gray-700 bg-gray-100 hover:bg-gray-200">
                                Batal
                            </x-button>
                            <x-button type="submit" variant="primary" class="shadow-lg shadow-indigo-500/20">
                                <span wire:loading.remove wire:target="save">Simpan Data</span>
                                <span wire:loading wire:target="save"><i
                                        class="mr-2 fa-solid fa-circle-notch fa-spin"></i> Menyimpan...</span>
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </section>

    {{-- MODAL IMPORT CSV --}}
    <section x-data="{ openImport: @entangle('isImportModalOpen') }">
        <template x-teleport="body">
            <div x-show="openImport" class="fixed inset-0 z-9999 flex items-center justify-center p-4" x-cloak>
                <div x-show="openImport" x-transition.opacity wire:click="closeImportModal"
                    class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>

                <div x-show="openImport" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    class="relative w-full max-w-2xl bg-white shadow-2xl dark:bg-gray-900 rounded-3xl p-8 overflow-hidden max-h-[90vh] flex flex-col">

                    {{-- Loading Overlay selama import berjalan --}}
                    <div wire:loading.flex wire:target="importData"
                        class="absolute inset-0 z-10 flex-col items-center justify-center rounded-3xl bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm transition-all">
                        <i class="mb-4 text-5xl text-indigo-600 fa-solid fa-spinner fa-spin"></i>
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white">Sedang Memproses Data...</h3>
                        <p class="text-sm text-gray-500">Mohon jangan tutup jendela ini hingga proses selesai.</p>
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-xl font-bold text-gray-900 dark:text-white">Import Ruangan (CSV)</h4>
                        <button wire:click="closeImportModal"
                            class="text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-white">
                            <i class="text-xl fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    {{-- VIEW: Form Upload (Jika belum selesai import) --}}
                    @if(!$isImportFinished)
                    <div class="mb-2 text-sm text-gray-600 dark:text-gray-400">
                        <p class="mb-2">Gunakan pemisah koma (<code>,</code>) atau titik koma (<code>;</code>) dengan
                            urutan Header baris pertama berikut:</p>
                        <div class="p-3 font-mono text-[10px] bg-gray-100 rounded-lg dark:bg-gray-800">
                            kode_ruangan, nama_ruangan, tipe, kapasitas, fasilitas, status_tersedia, icon
                        </div>
                    </div>
                    <div
                        class="mt-2 mb-4 p-3 text-xs border rounded-xl border-indigo-100 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:border-indigo-800 dark:text-indigo-300">

                        <div class="flex gap-2">

                            <i class="mt-0.5 fa-solid fa-circle-info"></i>

                            <div>
                                <strong>Catatan Import:</strong>

                                <ul class="mt-1 space-y-1 list-disc list-inside">
                                    <li>
                                        <strong>kode ruangan</strong> dan
                                        <strong>name</strong> wajib diisi.
                                    </li>

                                    <li><span class="font-bold">tipe:</span> Laboratorium, Aula, Ruang Rapat, Fasilitas
                                        Olahraga, Lainnya.</li>

                                    <li><span class="font-bold">status_tersedia:</span> 1 atau TRUE (Tersedia), 0 atau
                                        FALSE (Tidak).</li>
                                    <li>
                                        CSV dapat menggunakan pemisah
                                        <strong>koma (,)</strong> atau
                                        <strong>titik koma (;)</strong>.
                                    </li>
                                </ul>

                            </div>

                        </div>

                    </div>
                    <form wire:submit="importData" class="space-y-4">
                        <div>
                            <input type="file" wire:model="importFile" accept=".csv,.txt"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-800 dark:file:text-gray-300">
                            @error('importFile') <span class="block mt-1 text-xs text-red-500">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                            <x-button type="button" wire:click="closeImportModal" variant="secondary"
                                class="text-gray-700 bg-gray-100 hover:bg-gray-200">
                                Batal
                            </x-button>
                            <x-button type="submit" variant="primary" class="shadow-lg shadow-indigo-500/20">
                                <span wire:loading.remove wire:target="importFile">Proses Import</span>
                                <span wire:loading wire:target="importFile"><i
                                        class="mr-2 fa-solid fa-circle-notch fa-spin"></i> Uploading...</span>
                            </x-button>
                        </div>
                    </form>

                    {{-- VIEW: Hasil Report Import (Jika selesai) --}}
                    @else
                    <div class="flex-1 overflow-y-auto pr-2">
                        <div class="mb-4">
                            <h5 class="flex items-center gap-2 mb-2 font-bold text-emerald-600">
                                <i class="fa-solid fa-check-circle"></i> Berhasil Diimport ({{ count($successList) }})
                            </h5>
                            <div
                                class="p-3 text-xs overflow-y-auto max-h-40 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800/50">
                                @if(count($successList) > 0)
                                <ul class="space-y-1 list-disc list-inside">
                                    @foreach($successList as $success)
                                    <li>{{ $success }}</li>
                                    @endforeach
                                </ul>
                                @else
                                <p class="italic text-emerald-600/70">Tidak ada data yang berhasil.</p>
                                @endif
                            </div>
                        </div>

                        <div class="mb-4">
                            <h5 class="flex items-center gap-2 mb-2 font-bold text-red-600">
                                <i class="fa-solid fa-circle-xmark"></i> Gagal Diimport ({{ count($failedList) }})
                            </h5>
                            <div class="overflow-hidden border border-red-100 rounded-xl dark:border-red-800/50">
                                <div class="overflow-y-auto max-h-40 bg-red-50 dark:bg-red-900/20">
                                    @if(count($failedList) > 0)
                                    <table class="w-full text-xs text-left text-red-700 dark:text-red-400">
                                        <thead class="sticky top-0 bg-red-100 dark:bg-red-900">
                                            <tr>
                                                <th class="px-3 py-2 font-bold">Baris</th>
                                                <th class="px-3 py-2 font-bold">Kode</th>
                                                <th class="px-3 py-2 font-bold">Alasan</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-red-100 dark:divide-red-800/30">
                                            @foreach($failedList as $fail)
                                            <tr>
                                                <td class="px-3 py-2">{{ $fail['row'] }}</td>
                                                <td class="px-3 py-2">{{ $fail['kode'] }}</td>
                                                <td class="px-3 py-2">{{ $fail['reason'] }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    @else
                                    <p class="p-3 text-xs italic text-red-600/70">Semua data berhasil! Tidak ada yang
                                        gagal.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 mt-2 border-t border-gray-100 dark:border-gray-800">
                        <x-button type="button" wire:click="closeImportModal" variant="secondary"
                            class="text-gray-700 bg-gray-100 hover:bg-gray-200">
                            Tutup & Refresh Halaman
                        </x-button>
                    </div>
                    @endif
                </div>
            </div>
        </template>
    </section>

    <x-toast />
</div>
