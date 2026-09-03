<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app')] #[Title('Manajemen Pengguna')] class extends Component
{
    use WithPagination, WithFileUploads;

    /*
    |--------------------------------------------------------------------------
    | State Datatable & Filter
    |--------------------------------------------------------------------------
    */
    public $search = '';
    public $filterTipe = '';
    public string $statusFilter = '';
    public $view = 10;
    public $sortColumn = 'name';
    public $sortDirection = 'asc';
    /*
    |--------------------------------------------------------------------------
    | State Modal
    |--------------------------------------------------------------------------
    */
    public $isModalOpen = false;
    public $isImportModalOpen = false;
    public $isResetModalOpen = false;

    /*
    |--------------------------------------------------------------------------
    | Form User
    |--------------------------------------------------------------------------
    */
    public ?User $editingUser = null;

    public $name = '';
    public $username = '';
    public $no_hp = '';
    public $password = '';
    public $role = '';
    public $status = true;
    public $note = '';

    public array $selectedRoles = [];

    /*
    |--------------------------------------------------------------------------
    | Import
    |--------------------------------------------------------------------------
    */
    public $importFile = null;
    public $isImportFinished = false;
    public array $successList = [];
    public array $failedList = [];
    public int $successCount = 0;

    /*
    |--------------------------------------------------------------------------
    | Reset Password
    |--------------------------------------------------------------------------
    */
    public $selectedUserId = null;
    public $selectedUserName = null;
    public $selectedUsername = null;

    /*
    |--------------------------------------------------------------------------
    | Lifecycle / Pagination
    |--------------------------------------------------------------------------
    */
    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterTipe()
    {
        $this->resetPage();
    }

    public function updatingView()
    {
        $this->resetPage();
    }

    /*
    |--------------------------------------------------------------------------
    | Sorting
    |--------------------------------------------------------------------------
    */
    public function sort($column)
    {
        $allowedColumns = [
            'name',
            'username',
            'no_hp',
            'role',
            'status',
        ];

        if (!in_array($column, $allowedColumns)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection =
                $this->sortDirection === 'asc'
                    ? 'desc'
                    : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Query Data
    |--------------------------------------------------------------------------
    */
    public function with(): array
    {
        $hasRolePackage = class_exists(Role::class);

        $query = $hasRolePackage
            ? User::with('roles')
            : User::query();


        // jika ada filter status
        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter === 'active' ? 1 : 0);
        }
        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */
        if (trim($this->search) !== '') {
            $search = trim($this->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('username', 'like', '%' . $search . '%')
                    ->orWhere('no_hp', 'like', '%' . $search . '%');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Role / Tipe
        |--------------------------------------------------------------------------
        */
        if ($this->filterTipe !== '') {
            $query->where('role', $this->filterTipe);
        }

        /*
        |--------------------------------------------------------------------------
        | Return
        |--------------------------------------------------------------------------
        */
        return [
            'users' => $query
                ->orderBy($this->sortColumn, $this->sortDirection)
                ->paginate((int) $this->view),

            'availableRoles' => $hasRolePackage
                ? Role::orderBy('name')->get()
                : collect(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Tambah User
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        $this->resetForm();
        $this->isModalOpen = true;
    }

    /*
    |--------------------------------------------------------------------------
    | Edit User
    |--------------------------------------------------------------------------
    */
    public function edit(User $user)
    {
        $this->resetForm();

        $this->editingUser = $user;

        $this->name = $user->name;
        $this->username = $user->username;
        $this->no_hp = $user->no_hp;

        $this->role = $user->role instanceof \UnitEnum
            ? $user->role->value
            : $user->role;

        $this->status = (bool) $user->status;
        $this->note = $user->note;

        if (
            class_exists(Role::class) &&
            method_exists($user, 'roles')
        ) {
            $this->selectedRoles = $user->roles
                ->pluck('name')
                ->toArray();
        }

        $this->isModalOpen = true;
    }

    /*
    |--------------------------------------------------------------------------
    | Simpan User
    |--------------------------------------------------------------------------
    */
    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',

            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'username')
                    ->ignore($this->editingUser?->id),
            ],

            'no_hp' => 'nullable|string|max:20',

            'role' => 'required|string|max:50',

            'status' => 'required',

            'note' => 'nullable|string',
        ];

        /*
        |--------------------------------------------------------------------------
        | Password hanya wajib saat tambah
        |--------------------------------------------------------------------------
        */
        if (!$this->editingUser) {
            $rules['password'] = 'required|string|min:6';
        } else {
            $rules['password'] = 'nullable|string|min:6';
        }

        $this->validate($rules);

        $data = [
            'name' => trim($this->name),
            'username' => trim($this->username),
            'no_hp' => $this->no_hp
                ? trim($this->no_hp)
                : null,

            'role' => trim($this->role),

            'status' => trim($this->status),

            'note' => $this->note
                ? trim($this->note)
                : null,
        ];

        /*
        |--------------------------------------------------------------------------
        | Password
        |--------------------------------------------------------------------------
        */
        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        /*
        |--------------------------------------------------------------------------
        | Update / Create
        |--------------------------------------------------------------------------
        */
        if ($this->editingUser) {
            $this->editingUser->update($data);

            $message = 'Data pengguna berhasil diperbarui!';
        } else {
            User::create($data);

            $message = 'Pengguna baru berhasil ditambahkan!';
        }

        $this->closeModal();

        $this->dispatch(
            'toast',
            type: 'success',
            message: $message
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Toggle Status
    |--------------------------------------------------------------------------
    */
    public function toggleStatus(User $user)
    {
        $user->update([
            'status' => !$user->status,
        ]);

        $statusText = $user->status ? 'active' : 'non-active';

        $this->dispatch(
            'toast',
            type: 'success',
            message: "Status pengguna berhasil {$statusText}!"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | KONFIRMASI RESET PASSWORD
    |--------------------------------------------------------------------------
    */
    public function confirmReset($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            $this->dispatch(
                'toast',
                type: 'error',
                message: 'Data pengguna tidak ditemukan.'
            );

            return;
        }

        $this->selectedUserId = $user->id;
        $this->selectedUserName = $user->name;
        $this->selectedUsername = $user->username;

        $this->isResetModalOpen = true;
    }

    /*
    |--------------------------------------------------------------------------
    | RESET PASSWORD
    |--------------------------------------------------------------------------
    */
    public function resetData()
    {
        if (!$this->selectedUserId) {
            $this->dispatch(
                'toast',
                type: 'error',
                message: 'Pengguna yang akan di-reset belum dipilih.'
            );

            return;
        }

        $user = User::find($this->selectedUserId);

        if (!$user) {
            $this->closeResetModal();

            $this->dispatch(
                'toast',
                type: 'error',
                message: 'Data pengguna tidak ditemukan.'
            );

            return;
        }

        $user->update([
            'password' => Hash::make('sekolah123'),
        ]);

        $userName = $user->name;

        $this->closeResetModal();

        $this->dispatch(
            'toast',
            type: 'success',
            message: "Password {$userName} berhasil di-reset menjadi: sekolah123"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Buka Modal Import
    |--------------------------------------------------------------------------
    */
    public function openImportModal()
    {
        $this->resetImportState();
        $this->resetValidation();

        $this->isImportModalOpen = true;
    }

    /*
    |--------------------------------------------------------------------------
    | Import CSV
    |--------------------------------------------------------------------------
    */
    public function importData(): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $this->successList = [];
        $this->failedList = [];
        $this->successCount = 0;

        $handle = null;
        $transactionStarted = false;

        try {
            $path = $this->importFile->getRealPath();

            if (!$path || !is_readable($path)) {
                throw new \RuntimeException('File import tidak dapat dibaca.');
            }

            $firstLine = file_get_contents($path, false, null, 0, 4096);

            if ($firstLine === false) {
                throw new \RuntimeException('File CSV tidak dapat dibaca.');
            }

            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $handle = fopen($path, 'r');

            if (!$handle) {
                throw new \RuntimeException('File CSV tidak dapat dibuka.');
            }

            $header = fgetcsv($handle, 0, $delimiter);

            if (!$header) {
                throw new \RuntimeException('File CSV kosong atau tidak valid.');
            }

            $header = array_map(
                fn ($value) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value))),
                $header
            );

            foreach (['username', 'name'] as $column) {
                if (!in_array($column, $header, true)) {
                    throw new \RuntimeException("Kolom wajib '{$column}' tidak ditemukan pada CSV.");
                }
            }

            $defaultPasswordHash = Hash::make('sekolah123');
            $rows = [];
            $rowNumber = 1;

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;

                if (count($row) === 1 && trim((string) ($row[0] ?? '')) === '') {
                    continue;
                }

                if (count($header) !== count($row)) {
                    $this->failedList[] = [
                        'row' => $rowNumber,
                        'kode' => $row[0] ?? '-',
                        'reason' => 'Jumlah kolom tidak sesuai dengan header.',
                    ];
                    continue;
                }

                $rowData = array_combine($header, $row);
                $username = trim((string) ($rowData['username'] ?? ''));
                $name = trim((string) ($rowData['name'] ?? ''));

                if ($username === '') {
                    $this->failedList[] = [
                        'row' => $rowNumber,
                        'kode' => '-',
                        'reason' => 'Username wajib diisi.',
                    ];
                    continue;
                }

                if ($name === '') {
                    $this->failedList[] = [
                        'row' => $rowNumber,
                        'kode' => $username,
                        'reason' => 'Nama wajib diisi.',
                    ];
                    continue;
                }

                $roleValue = trim((string) ($rowData['role'] ?? '')) ?: 'siswa';
                $statusValue = strtolower(trim((string) ($rowData['status'] ?? 'active')));

                $status = match ($statusValue) {
                    'active', 'aktif', '1', 'true' => 1,
                    'non-active', 'nonaktif', 'non-aktif', 'inactive', '0', 'false' => 0,
                    default => 1,
                };

                $password = trim((string) ($rowData['password'] ?? ''));

                $rows[] = [
                    'username' => $username,
                    'name' => $name,
                    'no_hp' => trim((string) ($rowData['no_hp'] ?? '')) ?: null,
                    'role' => $roleValue,
                    'note' => trim((string) ($rowData['note'] ?? '')) ?: null,
                    'password' => $password !== '' ? Hash::make($password) : $defaultPasswordHash,
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            fclose($handle);
            $handle = null;

            if (empty($rows)) {
                $this->importFile = null;
                $this->isImportFinished = true;
                $this->dispatch('toast', type: 'warning', message: 'Tidak ada data valid yang dapat diimport.');
                return;
            }

            DB::beginTransaction();
            $transactionStarted = true;

            foreach (array_chunk($rows, 200) as $chunk) {
                User::upsert(
                    $chunk,
                    ['username'],
                    ['name', 'no_hp', 'role', 'note', 'password', 'status', 'updated_at']
                );

                $this->successCount += count($chunk);

                foreach ($chunk as $data) {
                    if (count($this->successList) >= 50) {
                        break;
                    }

                    $this->successList[] = "{$data['username']} — {$data['name']}";
                }
            }

            DB::commit();
            $transactionStarted = false;
            $this->importFile = null;
            $this->isImportFinished = true;

            $failedCount = count($this->failedList);
            $message = $failedCount > 0
                ? "Import selesai: {$this->successCount} berhasil, {$failedCount} gagal."
                : "Import selesai: {$this->successCount} data berhasil diproses.";

            $this->dispatch(
                'toast',
                type: $failedCount > 0 ? 'warning' : 'success',
                message: $message
            );
        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            if ($transactionStarted) {
                DB::rollBack();
            }

            report($e);

            $this->dispatch(
                'toast',
                type: 'error',
                message: 'Import gagal: ' . $e->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Reset State Import
    |--------------------------------------------------------------------------
    */
    private function resetImportState()
    {
        $this->importFile = null;

        $this->isImportFinished = false;

        $this->successCount = 0;

        $this->successList = [];

        $this->failedList = [];
    }

    /*
    |--------------------------------------------------------------------------
    | Tutup Modal Import
    |--------------------------------------------------------------------------
    */
    public function closeImportModal()
    {
        $this->isImportModalOpen = false;

        $this->resetImportState();

        $this->resetValidation();
    }

    /*
    |--------------------------------------------------------------------------
    | Tutup Modal Reset
    |--------------------------------------------------------------------------
    */
    public function closeResetModal()
    {
        $this->isResetModalOpen = false;

        $this->selectedUserId = null;
        $this->selectedUserName = null;
        $this->selectedUsername = null;
    }

    /*
    |--------------------------------------------------------------------------
    | Reset Form
    |--------------------------------------------------------------------------
    */
    public function resetForm()
    {
        $this->reset([
            'name',
            'username',
            'no_hp',
            'password',
            'role',
            'note',
            'selectedRoles',
            'editingUser',
        ]);

        $this->status = true;

        $this->resetValidation();
    }

    /*
    |--------------------------------------------------------------------------
    | Tutup Modal Form
    |--------------------------------------------------------------------------
    */
    public function closeModal()
    {
        $this->isModalOpen = false;
    }

    public function exportCSV(): StreamedResponse
    {
        $users = User::query()
            ->when($this->search, function ($query) {
                $term = '%' . $this->search . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('no_hp', 'like', $term);
                });
            })
            ->when($this->filterTipe, function ($query) {
                $query->where('role', $this->filterTipe);
            })
            ->when($this->statusFilter !== '', function ($query) {
                $query->where(
                    'status',
                    $this->statusFilter === 'active' ? 1 : 0
                );
            })
            ->orderBy('name')
            ->get();

        $filename = 'users-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($users) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'username',
                'name',
                'no_hp',
                'role',
                'note',
                'status',
            ]);

            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->username,
                    $user->name,
                    $user->no_hp,
                    $user->role,
                    $user->note,
                    $user->status ? 'active' : 'non-active',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
};

?>

<div>

    {{-- ========================================================= --}}
    {{-- HEADER --}}
    {{-- ========================================================= --}}
    <div class="flex flex-col gap-2 mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
            Manajemen Pengguna & Role
        </h1>

        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
            Kelola master data pengguna, hak akses, dan status akun di sini.
        </p>
    </div>

    {{-- ========================================================= --}}
    {{-- CONTAINER --}}
    {{-- ========================================================= --}}
    <div class="overflow-hidden transition-all bg-white border border-gray-200 shadow-sm rounded-3xl dark:bg-gray-900 dark:border-gray-800">

        {{-- ===================================================== --}}
        {{-- TOP ACTION --}}
        {{-- ===================================================== --}}
        <div class="flex flex-col items-center justify-between gap-4 p-4 border-b border-gray-100 md:flex-row dark:border-gray-800">

            <x-button
                type="button"
                wire:click="openImportModal"
                variant="success"
                class="flex items-center gap-2"
            >
                <i class="fa-solid fa-file-import"></i>
                Import CSV
            </x-button>

            <x-button
                type="button"
                wire:click="exportCSV"
                variant="secondary"
                class="flex items-center gap-2"
            >
                <i class="fa-solid fa-file-export"></i>
                Export CSV
            </x-button>

            <x-button
                type="button"
                wire:click="create"
                variant="primary"
                class="flex items-center gap-2 shadow-md shadow-indigo-500/20"
            >
                <i class="ti ti-plus"></i>
                Tambah Pengguna
            </x-button>

        </div>

        {{-- ===================================================== --}}
        {{-- FILTER --}}
        {{-- ===================================================== --}}
        <div class="flex flex-col items-center justify-between gap-4 p-6 border-b border-gray-100 md:flex-row dark:border-gray-800">

            <div class="flex flex-wrap items-center w-full gap-4 md:w-auto">

                {{-- Tampil --}}
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-gray-400 uppercase">
                        Tampil
                    </span>

                    <select
                        wire:model.live="view"
                        class="px-3 py-2 text-xs font-bold border-none outline-none bg-gray-50 dark:bg-gray-800 rounded-xl focus:ring-indigo-500 dark:text-white"
                    >
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>

                {{-- Filter --}}
                <div class="flex items-center gap-2">

                    <span class="text-xs font-bold text-gray-400 uppercase">
                        Filter Role
                    </span>

                    <select
                        wire:model.live="filterTipe"
                        class="px-3 py-2 text-xs font-bold border-none outline-none bg-gray-50 dark:bg-gray-800 rounded-xl focus:ring-indigo-500 dark:text-white"
                    >
                        <option value="">
                            Semua Role
                        </option>

                        <option value="admin">
                            Admin
                        </option>

                        <option value="guru/staff">
                            Staff/Guru
                        </option>

                        <option value="siswa">
                            Siswa
                        </option>
                    </select>

                </div>

                {{-- Status --}}
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-gray-400 uppercase">Status</span>
                    <select wire:model.live="statusFilter" class="rounded-xl border-none bg-gray-50 px-3 py-2 text-xs font-bold outline-none focus:ring-indigo-500 dark:bg-gray-800 dark:text-white">
                        <option value="">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="non-active">Non-Aktif</option>
                    </select>
                </div>

            </div>

            {{-- Search --}}
            <div class="relative w-full md:w-80">

                <i class="absolute text-gray-400 -translate-y-1/2 ti ti-search left-4 top-1/2"></i>

                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    class="w-full bg-gray-50 dark:bg-gray-800 border-none rounded-2xl pl-11 pr-4 py-2.5 text-xs font-bold focus:ring-2 focus:ring-indigo-500 dark:text-white transition-all outline-none"
                    placeholder="Cari nama, username, no hp..."
                >

            </div>

        </div>

        {{-- ===================================================== --}}
        {{-- TABLE --}}
        {{-- ===================================================== --}}
        <div class="relative min-h-[300px] overflow-x-auto">

            {{-- Loading --}}
            <div
                wire:loading.flex
                wire:target="search,filterTipe,view,sort,toggleStatus"
                class="absolute inset-0 z-10 items-center justify-center bg-white/50 dark:bg-gray-900/50 backdrop-blur-[2px]"
            >
                <div class="flex flex-col items-center gap-2">

                    <i class="text-4xl text-indigo-600 ti ti-loader-2 animate-spin"></i>

                    <span class="text-[10px] font-bold tracking-widest text-indigo-600 uppercase">
                        Memuat...
                    </span>

                </div>
            </div>

            <table class="w-full text-left border-collapse">

                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-800">

                        <th class="w-12 px-6 py-4 text-center text-gray-400">
                            #
                        </th>

                        <th
                            class="px-6 py-4 text-xs text-gray-400 uppercase cursor-pointer hover:text-indigo-600"
                            wire:click="sort('name')"
                        >
                            Pengguna

                            @if($sortColumn == 'name')
                                {{ $sortDirection == 'asc' ? '↑' : '↓' }}
                            @endif
                        </th>

                        <th
                            class="px-6 py-4 text-xs text-gray-400 uppercase cursor-pointer hover:text-indigo-600"
                            wire:click="sort('no_hp')"
                        >
                            No. HP

                            @if($sortColumn == 'no_hp')
                                {{ $sortDirection == 'asc' ? '↑' : '↓' }}
                            @endif
                        </th>

                        <th class="px-6 py-4 text-xs text-gray-400 uppercase">
                            Roles
                        </th>

                        <th class="px-6 py-4 text-xs text-gray-400 uppercase">
                            Status
                        </th>

                        <th class="px-6 py-4 text-xs text-center text-gray-400 uppercase">
                            Aksi
                        </th>

                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">

                    @forelse ($users as $index => $user)

                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/40">

                            {{-- No --}}
                            <td class="text-xs px-6 py-4 text-center text-gray-500">
                                {{ $users->firstItem() + $index }}
                            </td>

                            {{-- User --}}
                            <td class="text-xs px-6 py-4">

                                <div class="font-bold text-gray-900 dark:text-white">
                                    {{ $user->name }}
                                </div>

                                <div class="text-xs text-gray-500">
                                    {{ $user->username }}
                                </div>

                            </td>

                            {{-- Phone --}}
                            <td class="text-xs px-6 py-4 text-gray-600 dark:text-gray-400">
                                {{ $user->no_hp ?: '-' }}
                            </td>

                            {{-- Role --}}
                            <td class="text-xs px-6 py-4">

                                <div class="mb-1 font-bold text-indigo-600 uppercase">
                                    {{
                                        $user->role instanceof \UnitEnum
                                            ? $user->role->value
                                            : $user->role
                                    }}
                                </div>

                                @if(class_exists(Role::class) && method_exists($user, 'roles'))

                                    <div class="flex flex-wrap gap-1">

                                        @foreach($user->roles as $roleItem)

                                            <span class="px-2 py-0.5 text-[9px] font-bold text-white bg-gray-600 rounded-md">
                                                {{ $roleItem->name }}
                                            </span>

                                        @endforeach

                                    </div>

                                @endif

                            </td>

                            {{-- Status --}}
                            <td class="text-xs px-6 py-4">

                                <button
                                    type="button"
                                    wire:click="toggleStatus({{ $user->id }})"
                                    wire:loading.attr="disabled"
                                    class="px-3 py-1 rounded-full text-[10px] font-bold uppercase transition-all
                                    {{ $user->status
                                        ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'
                                        : 'bg-red-100 text-red-700 hover:bg-red-200'
                                    }}"
                                >
                                    {{ $user->status ? 'Aktif' : 'Non-Aktif' }}
                                </button>

                            </td>

                            {{-- Action --}}
                            <td class="text-xs px-6 py-4 text-center">

                                <div class="flex items-center justify-center gap-2">

                                    {{-- Reset Password --}}
                                    <button
                                        type="button"
                                        wire:click="confirmReset({{ $user->id }})"
                                        title="Reset Password"
                                        class="p-2 text-amber-600 bg-amber-100 rounded-lg hover:bg-amber-200 transition-colors"
                                    >
                                        <i class="ti ti-key"></i>
                                    </button>

                                    {{-- Edit --}}
                                    <button wire:click="edit({{ $user->id }})" title="Edit Data" class="p-2 text-blue-600 transition-colors bg-blue-100 rounded-lg hover:bg-blue-200">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="6"
                                class="px-6 py-12 font-medium text-center text-gray-400"
                            >
                                Tidak ada data pengguna yang ditemukan.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        {{-- ===================================================== --}}
        {{-- PAGINATION --}}
        {{-- ===================================================== --}}
        <div class="p-6 border-t border-gray-100 dark:border-gray-800">
            {{ $users->links() }}
        </div>

    </div>

    {{-- ========================================================= --}}
    {{-- MODAL TAMBAH / EDIT --}}
    {{-- ========================================================= --}}
    <section x-data="{ open: @entangle('isModalOpen') }">

        <template x-teleport="body">

            <div
                x-show="open"
                class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                x-cloak
            >

                <div
                    x-show="open"
                    x-transition.opacity
                    wire:click="closeModal"
                    class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"
                ></div>

                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    class="relative z-10 w-full max-w-3xl p-8 overflow-y-auto bg-white shadow-2xl dark:bg-gray-900 rounded-3xl max-h-[90vh]"
                >

                    {{-- Header --}}
                    <div class="flex items-center justify-between mb-6">

                        <h4 class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ $editingUser ? 'Edit Pengguna' : 'Tambah Pengguna Baru' }}
                        </h4>

                        <button
                            type="button"
                            wire:click="closeModal"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-white transition-colors"
                        >
                            <i class="text-xl ti ti-x"></i>
                        </button>

                    </div>

                    {{-- Form --}}
                    <form
                        wire:submit="save"
                        class="space-y-4"
                    >

                        {{-- Nama & Username --}}
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                            <x-input
                                wire:model="name"
                                label="Nama Lengkap"
                                name="name"
                                type="text"
                                placeholder="Masukkan nama"
                                required
                            />

                            <x-input
                                wire:model="username"
                                label="Username"
                                name="username"
                                type="text"
                                placeholder="Masukkan username"
                                required
                            />

                        </div>

                        {{-- HP & Password --}}
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                            <x-input
                                wire:model="no_hp"
                                label="No. Handphone"
                                name="no_hp"
                                type="text"
                                placeholder="08123xxxx"
                            />

                            <x-input
                                wire:model="password"
                                label="Password"
                                name="password"
                                type="password"
                                placeholder="{{ $editingUser ? 'Kosongkan jika tidak diubah' : 'Minimal 6 karakter' }}"
                            />

                        </div>

                        {{-- Tipe & Status --}}
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                            {{-- Role --}}
                            <div>

                                <label class="block mb-1 text-sm font-bold text-gray-700 dark:text-gray-300">
                                    Role
                                </label>

                                <select
                                    wire:model="role"
                                    class="w-full px-4 py-2.5 text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none dark:text-white"
                                >

                                    <option value="">
                                        -- Pilih Role --
                                    </option>

                                    <option value="admin">
                                        Admin
                                    </option>

                                    <option value="guru/staff">
                                        Staff/Guru
                                    </option>

                                    <option value="siswa">
                                        Siswa
                                    </option>

                                </select>

                                @error('role')
                                    <span class="block mt-1 text-xs text-red-500">
                                        {{ $message }}
                                    </span>
                                @enderror

                            </div>

                            {{-- Status --}}
                            <div>

                                <label class="block mb-1 text-sm font-bold text-gray-700 dark:text-gray-300">
                                    Status Akun
                                </label>

                                <select
                                    wire:model="status"
                                    class="w-full px-4 py-2.5 text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none dark:text-white"
                                >
                                    <option value="1">
                                        Aktif
                                    </option>

                                    <option value="0">
                                        Tidak Aktif
                                    </option>
                                </select>

                            </div>

                        </div>

                        {{-- Note --}}
                        <div>

                            <label class="block mb-1 text-sm font-bold text-gray-700 dark:text-gray-300">
                                Catatan Khusus (Opsional)
                            </label>

                            <textarea
                                wire:model="note"
                                rows="2"
                                class="w-full px-4 py-3 text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none dark:text-white"
                                placeholder="Tambahkan keterangan..."
                            ></textarea>

                            @error('note')
                                <span class="block mt-1 text-xs text-red-500">
                                    {{ $message }}
                                </span>
                            @enderror

                        </div>

                        {{-- Actions --}}
                        <div class="flex justify-end gap-3 pt-4 mt-6 border-t border-gray-100 dark:border-gray-800">

                            <x-button
                                type="button"
                                wire:click="closeModal"
                                variant="secondary"
                                class="text-gray-700 bg-gray-100 hover:bg-gray-200"
                            >
                                Batal
                            </x-button>

                            <x-button
                                type="submit"
                                variant="primary"
                                wire:loading.attr="disabled"
                                wire:target="save"
                                class="shadow-lg shadow-indigo-500/20"
                            >
                                <span wire:loading.remove wire:target="save">
                                    Simpan Data
                                </span>

                                <span wire:loading wire:target="save">
                                    <i class="mr-2 ti ti-loader-2 animate-spin"></i>
                                    Menyimpan...
                                </span>

                            </x-button>

                        </div>

                    </form>

                </div>

            </div>

        </template>

    </section>

    {{-- ========================================================= --}}
    {{-- MODAL IMPORT CSV --}}
    {{-- ========================================================= --}}
    <section x-data="{ openImport: @entangle('isImportModalOpen') }">
        <template x-teleport="body">
            <div x-show="openImport" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
                <div x-show="openImport" x-transition.opacity wire:click="closeImportModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
                <div x-show="openImport" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100 translate-y-0" x-transition:leave-end="opacity-0 scale-95 translate-y-4" class="relative z-10 flex flex-col w-full max-w-2xl max-h-[90vh] overflow-hidden p-6 sm:p-8 bg-white shadow-2xl dark:bg-gray-900 rounded-3xl">
                    <div wire:loading.flex wire:target="importData" class="absolute inset-0 z-20 flex-col items-center justify-center bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm rounded-3xl">
                        <i class="mb-4 text-5xl text-indigo-600 fa-solid fa-spinner fa-spin"></i>
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white">Sedang Memproses Data...</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Mohon jangan menutup jendela ini.</p>
                    </div>

                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 dark:text-white">Import Pengguna</h4>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Import data pengguna melalui file CSV.</p>
                        </div>
                        <button type="button" wire:click="closeImportModal" class="flex items-center justify-center w-9 h-9 text-gray-400 rounded-xl hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-white">
                            <i class="text-xl fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    @if(!$isImportFinished)
                        <div class="mb-5 text-sm text-gray-600 dark:text-gray-400">
                            <p class="mb-2">Gunakan file CSV dengan header berikut:</p>
                            <div class="p-3 font-mono text-[10px] break-all bg-gray-100 rounded-lg dark:bg-gray-800">username, name, no_hp, role, note, password, status</div>
                            <div class="p-3 mt-3 text-xs border rounded-xl border-indigo-100 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:border-indigo-800 dark:text-indigo-300">
                                <div class="flex gap-2">
                                    <i class="mt-0.5 fa-solid fa-circle-info"></i>
                                    <div>
                                        <strong>Catatan Import</strong>
                                        <ul class="mt-1 space-y-1 list-disc list-inside">
                                            <li><strong>username</strong> dan <strong>name</strong> wajib diisi.</li>
                                            <li>Username yang sudah ada akan diperbarui.</li>
                                            <li>Username baru akan ditambahkan.</li>
                                            <li>Password kosong otomatis menjadi <strong>sekolah123</strong>.</li>
                                            <li>Status dapat diisi <strong>active</strong> atau <strong>non-active</strong>.</li>
                                            <li>CSV dapat menggunakan pemisah <strong>koma (,)</strong> atau <strong>titik koma (;)</strong>.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form wire:submit="importData" class="space-y-4">
                            <div>
                                <input type="file" wire:model="importFile" accept=".csv,.txt" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-800 dark:file:text-gray-300">
                                @error('importFile')
                                    <span class="block mt-1 text-xs text-red-500">{{ $message }}</span>
                                @enderror
                            </div>

                            <div wire:loading wire:target="importFile" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                <i class="mr-1 fa-solid fa-spinner fa-spin"></i>Mengunggah file...
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <x-button type="button" wire:click="closeImportModal" variant="secondary" class="text-gray-700 bg-gray-100 hover:bg-gray-200">
                                    Batal
                                </x-button>
                                <x-button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="importData" class="shadow-lg shadow-indigo-500/20">
                                    <span wire:loading.remove wire:target="importData"><i class="mr-2 fa-solid fa-file-import"></i>Proses Import</span>
                                    <span wire:loading wire:target="importData"><i class="mr-2 fa-solid fa-circle-notch fa-spin"></i>Memproses...</span>
                                </x-button>
                            </div>
                        </form>
                    @else
                        <div class="flex-1 pr-1 overflow-y-auto space-y-5">
                            <div>
                                <div class="flex items-center justify-between gap-3 mb-2">
                                    <h5 class="flex items-center gap-2 font-bold text-emerald-600">
                                        <i class="fa-solid fa-circle-check"></i>Berhasil Diproses
                                    </h5>
                                    <span class="px-2.5 py-1 text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        {{ $successCount }}
                                    </span>
                                </div>

                                <div class="p-3 overflow-y-auto text-xs border max-h-56 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border-emerald-100 dark:border-emerald-800/50">
                                    @if(!empty($successList))
                                        <div class="space-y-1">
                                            @foreach($successList as $success)
                                                <div class="flex items-center gap-2 px-3 py-2 bg-white/70 dark:bg-emerald-950/30 rounded-lg">
                                                    <i class="text-[10px] fa-solid fa-check text-emerald-500"></i>
                                                    <span class="break-all">{{ $success }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="italic text-emerald-600/70">Tidak ada data yang berhasil diproses.</p>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between gap-3 mb-2">
                                    <h5 class="flex items-center gap-2 font-bold text-red-600">
                                        <i class="fa-solid fa-circle-xmark"></i>Gagal Diproses
                                    </h5>
                                    <span class="px-2.5 py-1 text-[10px] font-bold rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">
                                        {{ count($failedList) }}
                                    </span>
                                </div>

                                <div class="overflow-hidden border border-red-100 rounded-xl dark:border-red-800/50">
                                    <div class="overflow-y-auto max-h-56 bg-red-50 dark:bg-red-900/20">
                                        @if(!empty($failedList))
                                            <table class="w-full text-xs text-left text-red-700 dark:text-red-400">
                                                <thead class="sticky top-0 bg-red-100 dark:bg-red-900">
                                                    <tr>
                                                        <th class="px-3 py-2 font-bold">Baris</th>
                                                        <th class="px-3 py-2 font-bold">Username</th>
                                                        <th class="px-3 py-2 font-bold">Alasan</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-red-100 dark:divide-red-800/30">
                                                    @foreach($failedList as $fail)
                                                        <tr>
                                                            <td class="px-3 py-2">{{ $fail['row'] ?? '-' }}</td>
                                                            <td class="px-3 py-2 break-all">{{ $fail['kode'] ?? '-' }}</td>
                                                            <td class="px-3 py-2">{{ $fail['reason'] ?? 'Data tidak valid.' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @else
                                            <p class="p-3 text-xs italic text-red-600/70">Semua data berhasil diproses.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4 mt-5 border-t border-gray-100 dark:border-gray-800">
                            <x-button type="button" wire:click="closeImportModal" variant="secondary" class="text-gray-700 bg-gray-100 hover:bg-gray-200">
                                <i class="mr-2 fa-solid fa-xmark"></i>Tutup
                            </x-button>
                        </div>
                    @endif
                </div>
            </div>
        </template>
    </section>



    {{-- ========================================================= --}}
    {{-- MODAL RESET PASSWORD --}}
    {{-- ========================================================= --}}
    <section x-data="{ openReset: @entangle('isResetModalOpen') }">

        <template x-teleport="body">

            <div
                x-show="openReset"
                class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                x-cloak
            >

                {{-- Backdrop --}}
                <div
                    x-show="openReset"
                    x-transition.opacity
                    wire:click="closeResetModal"
                    class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"
                ></div>

                {{-- Modal --}}
                <div
                    x-show="openReset"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 translate-y-4"
                    class="relative z-10 flex flex-col items-center w-full max-w-md p-8 overflow-hidden text-center bg-white shadow-2xl dark:bg-gray-900 rounded-3xl"
                >

                    {{-- Loading --}}
                    <div
                        wire:loading.flex
                        wire:target="resetData"
                        class="absolute inset-0 z-20 flex-col items-center justify-center bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm rounded-3xl"
                    >

                        <i class="mb-4 text-5xl text-amber-600 fa-solid fa-spinner fa-spin"></i>

                        <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                            Mereset Password...
                        </h3>

                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Mohon tunggu sebentar.
                        </p>

                    </div>

                    {{-- Icon --}}
                    <div class="flex items-center justify-center w-20 h-20 mb-5 bg-amber-100 rounded-full dark:bg-amber-900/30">

                        <i class="text-4xl text-amber-600 fa-solid fa-key dark:text-amber-500"></i>

                    </div>

                    {{-- Title --}}
                    <h4 class="mb-2 text-2xl font-bold text-gray-900 dark:text-white">
                        Konfirmasi Reset Password
                    </h4>

                    <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
                        Apakah Anda yakin ingin mereset password pengguna berikut?
                    </p>

                    {{-- User Info --}}
                    <div class="w-full p-4 mb-4 text-left border border-gray-200 bg-gray-50 rounded-2xl dark:bg-gray-800 dark:border-gray-700">

                        <div class="mb-3">

                            <p class="text-[10px] font-bold tracking-wider text-gray-400 uppercase">
                                Nama Pengguna
                            </p>

                            <p class="font-bold text-gray-800 dark:text-white">
                                {{ $selectedUserName }}
                            </p>

                        </div>

                        <div>

                            <p class="text-[10px] font-bold tracking-wider text-gray-400 uppercase">
                                Username
                            </p>

                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $selectedUsername }}
                            </p>

                        </div>

                    </div>

                    {{-- Password Baru --}}
                    <div class="w-full p-4 mb-5 border border-amber-200 bg-amber-50 rounded-2xl dark:bg-amber-900/20 dark:border-amber-800">

                        <p class="mb-1 text-xs font-semibold text-amber-700 dark:text-amber-400">
                            Password Baru
                        </p>

                        <p class="text-xl font-bold tracking-wider text-amber-800 dark:text-amber-300">
                            sekolah123
                        </p>

                    </div>

                    <p class="mb-7 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                        Password lama akan diganti dan pengguna harus menggunakan
                        password baru tersebut untuk login.
                    </p>

                    {{-- Buttons --}}
                    <div class="flex w-full gap-3">

                        <x-button
                            type="button"
                            wire:click="closeResetModal"
                            variant="secondary"
                            class="justify-center flex-1 text-gray-700 bg-gray-100 hover:bg-gray-200"
                        >
                            Batal
                        </x-button>

                        <x-button
                            type="button"
                            wire:click="resetData"
                            wire:loading.attr="disabled"
                            wire:target="resetData"
                            class="justify-center flex-1 text-white bg-amber-600 hover:bg-amber-700 shadow-lg shadow-amber-500/20"
                        >

                            <span wire:loading.remove wire:target="resetData">
                                <i class="mr-2 fa-solid fa-key"></i>
                                Ya, Reset Password
                            </span>

                            <span wire:loading wire:target="resetData">
                                <i class="mr-2 fa-solid fa-spinner fa-spin"></i>
                                Memproses...
                            </span>

                        </x-button>

                    </div>

                </div>

            </div>

        </template>

    </section>

    <x-toast />

</div>