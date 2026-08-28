<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin Sekolah',
            'username' => 'admin_smansa',
            'password' => Hash::make('admin321'),
            'role' => 'admin',
            'status' => 'active',
            'note' => 'Akun Super Admin Sekolah',
        ]);

       
        $guru = User::create([
            'name' => 'M. Nurul Alam',
            'username' => 'm.nurulalam',
            'password' => Hash::make('alam321'),
            'role' => 'guru/staff',
            'status' => 'active',
            'note' => 'Operator Sekolah',
        ]);

        // 4. akun pengguna
        $siswa = User::create([
            'name' => 'Budi Santoso',
            'username' => 'budi.santoso',
            'password' => Hash::make('budi321'),
            'role' => 'siswa',
            'status' => 'active',
            'note' => 'XII Kartini 1',
        ]);
    }
}