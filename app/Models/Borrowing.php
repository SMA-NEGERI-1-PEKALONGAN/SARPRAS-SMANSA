<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Borrowing extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode_transaksi',
        'user_id',
        'approved_by', // Tambahkan ini
        'tujuan',
        'tanggal_mulai',
        'tanggal_selesai',
        'file_lampiran',
        'status',
        'catatan',
        'catatan_admin',
        'tanda_tangan',
    ];

    protected $casts = [
        'tanggal_mulai' => 'datetime',
        'tanggal_selesai' => 'datetime',
    ];

    // Relasi ke Pemohon (User)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relasi ke Admin yang Menyetujui/Menolak
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Relasi ke Detail Transaksi
    public function details(): HasMany
    {
        return $this->hasMany(BorrowingDetail::class);
    }
}