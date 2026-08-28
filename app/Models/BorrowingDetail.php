<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowingDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'borrowing_id',
        'room_id',
        'item_id',
        'jumlah',
        'status',
    ];

    protected $casts = [
        'jumlah' => 'integer',
    ];

    // Relasi balik ke Header Transaksi
    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }

    // Relasi ke Ruangan
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    // Relasi ke Barang
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}