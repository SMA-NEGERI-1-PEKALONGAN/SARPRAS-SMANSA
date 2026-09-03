<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // Import relasi HasMany

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode_ruangan',
        'nama_ruangan',
        'lokasi',
        'tipe',
        'kapasitas',
        'fasilitas',
        'deskripsi',
        'status_tersedia',
        'icon',
    ];

    protected $casts = [
        'status_tersedia' => 'boolean',
        'kapasitas' => 'integer',
    ];

    /**
     * Relasi ke Detail Peminjaman
     */
    public function borrowingDetails(): HasMany
    {
        return $this->hasMany(BorrowingDetail::class);
    }
}