<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // Import relasi HasMany

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode_barang',
        'nama_barang',
        'kategori',
        'jumlah_total',
        'deskripsi',
        'bisa_dipinjam',
        'icon',
    ];

    protected $casts = [
        'bisa_dipinjam' => 'boolean',
        'jumlah_total' => 'integer',
    ];

    /**
     * Relasi ke Detail Peminjaman
     */
    public function borrowingDetails(): HasMany
    {
        return $this->hasMany(BorrowingDetail::class);
    }
}