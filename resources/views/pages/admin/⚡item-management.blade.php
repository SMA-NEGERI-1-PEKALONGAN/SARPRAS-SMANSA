<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Item;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

new #[Layout('layouts.app')] #[Title('Manajemen Barang (Inventaris)')] class extends Component 
{
    use WithPagination, WithFileUploads;

    // State Datatable & Filter
    public $search = '';
    public $filterKategori = '';
    public $view = 10;
    public $sortColumn = 'kode_barang';
    public $sortDirection = 'asc';

    // State Modal Form CRUD
    public $isModalOpen = false;
    public ?Item $editingItem = null;

    // Fields Form CRUD
    public $kode_barang = '';
    public $nama_barang = '';
    public $kategori = '';
    public $jumlah_total = 1;
    public $deskripsi = '';
    public $bisa_dipinjam = true;
    public $icon = 'fa-solid fa-box';

    // State Modal & Field Import
    public $isImportModalOpen = false;
    public $importFile;
    
    // State Hasil Import
    public $isImportFinished = false;
    public $successList = [];
    public $failedList = [];

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterKategori() { $this->resetPage(); }
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
        $query = Item::query();

        if ($this->search) {
            $query->where(function($q) {
                $q->where('kode_barang', 'like', '%' . $this->search . '%')
                  ->orWhere('nama_barang', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filterKategori) {
            $query->where('kategori', $this->filterKategori);
        }

        return [
            'items' => $query->orderBy($this->sortColumn, $this->sortDirection)->paginate($this->view),
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

    public function edit(Item $item)
    {
        $this->resetForm();
        $this->editingItem = $item;
        
        $this->kode_barang = $item->kode_barang;
        $this->nama_barang = $item->nama_barang;
        $this->kategori = $item->kategori;
        $this->jumlah_total = $item->jumlah_total;
        $this->deskripsi = $item->deskripsi;
        $this->bisa_dipinjam = $item->bisa_dipinjam;
        $this->icon = $item->icon ?? 'fa-solid fa-box';
        
        $this->isModalOpen = true;
    }

    public function save()
    {
        $this->validate([
            'kode_barang' => ['required', 'string', 'max:50', Rule::unique('items')->ignore($this->editingItem?->id)],
            'nama_barang' => 'required|string|max:255',
            'kategori' => 'required|in:Elektronik,Olahraga,Laboratorium,Buku,Lainnya',
            'jumlah_total' => 'required|integer|min:1',
            'deskripsi' => 'nullable|string',
            'bisa_dipinjam' => 'boolean',
            'icon' => 'nullable|string|max:100',
        ]);

        $data = [
            'kode_barang' => strtoupper($this->kode_barang),
            'nama_barang' => $this->nama_barang,
            'kategori' => $this->kategori,
            'jumlah_total' => $this->jumlah_total,
            'deskripsi' => $this->deskripsi,
            'bisa_dipinjam' => (bool) $this->bisa_dipinjam,
            'icon' => $this->icon ?: 'fa-solid fa-box',
        ];

        if ($this->editingItem) {
            $this->editingItem->update($data);
            $msg = 'Data barang berhasil diperbarui!';
        } else {
            Item::create($data);
            $msg = 'Barang baru berhasil ditambahkan!';
        }

        $this->isModalOpen = false;
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    public function toggleStatus(Item $item)
    {
        $item->update(['bisa_dipinjam' => !$item->bisa_dipinjam]);
        $statusText = $item->bisa_dipinjam ? 'Bisa Dipinjam' : 'Tidak Bisa Dipinjam';
        $this->dispatch('toast', type: 'success', message: "Status barang diubah menjadi $statusText!");
    }

    public function resetForm()
    {
        $this->reset(['kode_barang', 'nama_barang', 'kategori', 'jumlah_total', 'deskripsi', 'editingItem']);
        $this->jumlah_total = 1;
        $this->bisa_dipinjam = true;
        $this->icon = 'fa-solid fa-box';
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
        $this->reset(['importFile', 'successList', 'failedList', 'isImportFinished']);
        $this->resetValidation('importFile');
        $this->isImportModalOpen = true;
    }

    public function closeImportModal()
    {
        $this->isImportModalOpen = false;
        $this->reset(['importFile', 'successList', 'failedList', 'isImportFinished']);
        $this->resetPage(); // Refresh data table
    }

    public function importData()
    {
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt|max:5120', // Max 5MB
        ], [
            'importFile.required' => 'Silakan pilih file CSV terlebih dahulu.',
            'importFile.mimes' => 'Format file harus berupa CSV.',
            'importFile.max' => 'Ukuran file maksimal 5MB.',
        ]);

        $this->successList = [];
        $this->failedList = [];
        $this->isImportFinished = false;

        try {
            $path = $this->importFile->getRealPath();
            $handle = fopen($path, "r");

            // 1. Deteksi Delimiter (, atau ;)
            $firstLine = fgets($handle);
            $delimiter = strpos($firstLine, ';') !== false ? ';' : ',';
            rewind($handle);

            // 2. Baca Header
            $header = fgetcsv($handle, 1000, $delimiter); 
            $rowNum = 2; // Mulai perhitungan dari baris ke-2 (setelah header)
            
            $allowedKategori = ['Elektronik', 'Olahraga', 'Laboratorium', 'Buku', 'Lainnya'];
            
            // Array penampung sementara untuk deteksi duplikat di dalam file CSV yang sama
            $processedCodes = [];

            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                // Skip baris kosong
                if (array_filter($row) === []) {
                    $rowNum++;
                    continue;
                }

                try {
                    // Validasi minimal 4 kolom & kode barang tidak kosong
                    if (count($row) < 4 || empty(trim($row[0]))) {
                        throw new \Exception("Format kolom tidak lengkap atau Kode Barang kosong.");
                    }

                    $kodeBarang = strtoupper(trim($row[0]));
                    $namaBarang = trim($row[1]);

                    if (empty($namaBarang)) {
                        throw new \Exception("Nama Barang tidak boleh kosong.");
                    }

                    // ==============================================================
                    // PENGECEKAN DUPLIKASI KODE BARANG
                    // ==============================================================
                    
                    // 1. Cek duplikasi di dalam file CSV yang sama
                    if (in_array($kodeBarang, $processedCodes)) {
                        throw new \Exception("Kode Barang duplikat di dalam file CSV yang Anda upload.");
                    }

                    // 2. Cek duplikasi di database
                    if (Item::where('kode_barang', $kodeBarang)->exists()) {
                        throw new \Exception("Kode Barang sudah terdaftar di sistem.");
                    }
                    // ==============================================================

                    $kategori = in_array(trim($row[2] ?? ''), $allowedKategori) ? trim($row[2]) : 'Lainnya';
                    $jumlahTotal = is_numeric($row[3] ?? null) ? (int)$row[3] : 1;
                    $deskripsi = $row[4] ?? null;
                    $bisaDipinjam = filter_var($row[5] ?? true, FILTER_VALIDATE_BOOLEAN);
                    $icon = !empty($row[6]) ? trim($row[6]) : 'fa-solid fa-box';

                    // Ubah updateOrCreate menjadi create, karena duplikasi sudah kita tolak di atas
                    Item::create([
                        'kode_barang' => $kodeBarang,
                        'nama_barang' => $namaBarang,
                        'kategori' => $kategori,
                        'jumlah_total' => $jumlahTotal,
                        'deskripsi' => $deskripsi,
                        'bisa_dipinjam' => $bisaDipinjam,
                        'icon' => $icon,
                    ]);

                    // Masukkan kode ke array penampung agar bisa dicek di baris berikutnya
                    $processedCodes[] = $kodeBarang;
                    $this->successList[] = "Baris $rowNum: $kodeBarang - $namaBarang";

                } catch (\Exception $e) {
                    $this->failedList[] = [
                        'row' => $rowNum,
                        'kode' => $row[0] ?? 'N/A',
                        'reason' => $e->getMessage() // Menampilkan pesan error dari throw Exception
                    ];
                }
                
                $rowNum++;
            }
            fclose($handle);
            
            $this->isImportFinished = true;
            $countSuccess = count($this->successList);
            $countFailed = count($this->failedList);
            
            if($countSuccess > 0) {
                $this->dispatch('toast', type: 'success', message: "$countSuccess data berhasil diproses.");
            }
            if($countFailed > 0) {
                $this->dispatch('toast', type: 'warning', message: "$countFailed data gagal di-import. Silakan cek detail.");
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error Import Barang: ' . $e->getMessage());
            $this->dispatch('toast', type: 'error', message: "Terjadi kesalahan saat memproses file: " . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- Header & Info Halaman --}}
    <div class="flex flex-col gap-2 mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Manajemen Barang</h1>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Kelola master data inventaris, jumlah stok, dan
            status izin peminjaman barang.</p>
    </div>

    {{-- Container Utama --}}
    <div
        class="transition-all bg-white border border-gray-200 shadow-sm overflow-clip rounded-3xl dark:bg-gray-900 dark:border-gray-800">

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
                <i class="fa-solid fa-plus"></i> Tambah Barang
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
                    <span class="text-xs font-bold text-gray-400 uppercase">Kategori</span>
                    <select wire:model.live="filterKategori"
                        class="px-3 py-2 text-xs font-bold border-none outline-none bg-gray-50 dark:bg-gray-800 rounded-xl focus:ring-indigo-500 dark:text-white">
                        <option value="">Semua Kategori</option>
                        <option value="Elektronik">Elektronik</option>
                        <option value="Olahraga">Olahraga</option>
                        <option value="Laboratorium">Laboratorium</option>
                        <option value="Buku">Buku</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </div>
            </div>

            <div class="relative w-full md:w-80">
                <i class="absolute text-gray-400 -translate-y-1/2 fa-solid fa-magnifying-glass left-4 top-1/2"></i>
                <input type="text" wire:model.live.debounce.300ms="search"
                    class="w-full bg-gray-50 dark:bg-gray-800 border-none rounded-2xl pl-11 pr-4 py-2.5 text-xs font-bold focus:ring-2 focus:ring-indigo-500 dark:text-white transition-all outline-none"
                    placeholder="Cari kode atau nama barang...">
            </div>
        </div>

        {{-- Tabel Data --}}
        <div class="relative overflow-x-auto font-mono text-sm min-h-[300px]">
            {{-- Loading overlay --}}
            <div wire:loading.flex wire:target="search, filterKategori, view, sort, toggleStatus, resetPage"
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
                            wire:click="sort('nama_barang')">
                            Barang {!! $sortColumn == 'nama_barang' ? ($sortDirection == 'asc' ? '↑' : '↓') : '' !!}
                        </th>
                        <th class="px-6 py-4 font-bold text-gray-400 uppercase cursor-pointer hover:text-indigo-600"
                            wire:click="sort('kategori')">
                            Kategori {!! $sortColumn == 'kategori' ? ($sortDirection == 'asc' ? '↑' : '↓') : '' !!}
                        </th>
                        <th class="px-6 py-4 font-bold text-center text-gray-400 uppercase cursor-pointer hover:text-indigo-600"
                            wire:click="sort('jumlah_total')">
                            Stok {!! $sortColumn == 'jumlah_total' ? ($sortDirection == 'asc' ? '↑' : '↓') : '' !!}
                        </th>
                        <th class="px-6 py-4 font-bold text-gray-400 uppercase">Izin Pinjam</th>
                        <th class="px-6 py-4 font-bold text-center text-gray-400 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($items as $index => $item)
                    <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/40">
                        <td class="px-6 py-4 text-center text-gray-500">{{ $items->firstItem() + $index }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex items-center justify-center w-10 h-10 text-indigo-600 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 dark:text-indigo-400">
                                    <i class="text-lg {{ $item->icon ?: 'fa-solid fa-box' }}"></i>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900 dark:text-white">{{ $item->nama_barang }}</div>
                                    <div class="text-xs font-bold text-indigo-600">{{ $item->kode_barang }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span
                                class="px-3 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded-full dark:bg-gray-800 dark:text-gray-300">
                                {{ $item->kategori }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span
                                class="text-lg font-bold text-gray-900 dark:text-white">{{ $item->jumlah_total }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <button wire:click="toggleStatus({{ $item->id }})" wire:loading.attr="disabled"
                                class="px-3 py-1 rounded-full text-[10px] font-bold uppercase transition-all {{ $item->bisa_dipinjam ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-red-100 text-red-700 hover:bg-red-200' }}">
                                {{ $item->bisa_dipinjam ? 'Diizinkan' : 'Dilarang' }}
                            </button>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="edit({{ $item->id }})" title="Edit Data"
                                    class="p-2 text-blue-600 transition-colors bg-blue-100 rounded-lg hover:bg-blue-200">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 font-medium text-center text-gray-400">Tidak ada data barang
                            yang ditemukan.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="p-6 border-t border-gray-100 dark:border-gray-800">
            {{ $items->links() }}
        </div>
    </div>

    {{-- MODAL TAMBAH/EDIT (SAMA SEPERTI SEBELUMNYA) --}}
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
                            {{ $editingItem ? 'Edit Barang' : 'Tambah Barang Baru' }}</h4>
                        <button wire:click="closeModal"
                            class="text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-white">
                            <i class="text-xl fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <form wire:submit="save" class="space-y-4">
                        {{-- Row 1: Kode & Nama --}}
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <x-input wire:model="kode_barang" label="Kode Barang" name="kode_barang" type="text"
                                    placeholder="Cth: ELK-PROY-01" required />
                                <span class="block mt-1 text-[10px] text-gray-400">Disarankan menggunakan pengkodean
                                    khusus.</span>
                            </div>
                            <x-input wire:model="nama_barang" label="Nama Barang" name="nama_barang" type="text"
                                placeholder="Cth: Proyektor Epson EB-X05" required />
                        </div>

                        {{-- Row 2: Kategori & Stok --}}
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="block mb-1 text-sm font-bold text-gray-700 dark:text-gray-300">Kategori
                                    Barang</label>
                                <select wire:model="kategori"
                                    class="w-full px-4 py-2.5 text-sm bg-gray-50 dark:bg-gray-800 border-none rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none dark:text-white">
                                    <option value="">-- Pilih Kategori --</option>
                                    <option value="Elektronik">Elektronik</option>
                                    <option value="Olahraga">Olahraga</option>
                                    <option value="Laboratorium">Laboratorium</option>
                                    <option value="Buku">Buku</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                                @error('kategori') <span class="mt-1 text-xs text-red-500">{{ $message }}</span>
                                @enderror
                            </div>

                            <x-input wire:model="jumlah_total" label="Jumlah Total (Stok)" name="jumlah_total"
                                type="number" min="1" required />
                        </div>

                        {{-- Icon FontAwesome dengan Preview --}}
                        <div>
                            <label class="block mb-1 text-sm font-bold text-gray-700 dark:text-gray-300">Icon Barang
                                (FontAwesome)</label>
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex items-center justify-center flex-shrink-0 w-12 text-indigo-600 h-11 bg-indigo-50 dark:bg-indigo-900/30 dark:text-indigo-400 rounded-xl">
                                    <i class="text-xl {{ $icon ?: 'fa-solid fa-box' }}"></i>
                                </div>
                                <div class="flex-1">
                                    <input type="text" wire:model.live.debounce.300ms="icon"
                                        class="w-full px-4 py-2.5 text-sm transition-all border-none outline-none bg-gray-50 dark:bg-gray-800 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white"
                                        placeholder="Contoh: fa-solid fa-video">
                                </div>
                            </div>
                            <span class="block mt-1 text-[10px] text-gray-400">Contoh: <code>fa-solid
                                    fa-basketball</code>, <code>fa-solid fa-microscope</code>.</span>
                        </div>

                        {{-- Deskripsi --}}
                        <div>
                            <label class="block mb-1 text-sm font-bold text-gray-700 dark:text-gray-300">Deskripsi /
                                Spesifikasi</label>
                            <textarea wire:model="deskripsi" rows="3"
                                class="w-full px-4 py-3 text-sm transition-all border-none outline-none bg-gray-50 dark:bg-gray-800 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white"
                                placeholder="Tuliskan spesifikasi, kelengkapan, atau info tambahan..."></textarea>
                        </div>

                        {{-- Status --}}
                        <div>
                            <label class="block mb-1 text-sm font-bold text-gray-700 dark:text-gray-300">Izin
                                Peminjaman</label>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" wire:model="bisa_dipinjam"
                                    class="w-5 h-5 text-indigo-600 transition-all border-gray-300 rounded focus:ring-indigo-500 bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Izinkan barang ini
                                    untuk dipinjam oleh siswa/guru</span>
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

    {{-- MODAL IMPORT CSV (UPDATED) --}}
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
                        <h4 class="text-xl font-bold text-gray-900 dark:text-white">Import Barang (CSV)</h4>
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
                            kode_barang, nama_barang, kategori, jumlah_total, deskripsi, bisa_dipinjam, icon
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
                                    <li><span class="font-bold">kategori:</span> Elektronik, Olahraga, Laboratorium, Buku,
                                        Lainnya.</li>
                                    <li><span class="font-bold">izin:</span> 1 atau TRUE (Bisa Dipinjam), 0 atau FALSE (Tidak).
                                    </li>
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
