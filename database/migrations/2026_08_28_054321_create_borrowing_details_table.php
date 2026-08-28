<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowing_details', function (Blueprint $table) {
            $table->id();
            // Relasi ke tabel Header
            $table->foreignId('borrowing_id')->constrained()->cascadeOnDelete();
            
            // Item atau Ruang yang dipinjam
            $table->foreignId('room_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->cascadeOnDelete();
            
            $table->integer('jumlah')->default(1);
            
            // Status per item (Karena admin bisa setuju pinjam Proyektor, tapi nolak pinjam Ruang A)
            $table->enum('status', [
                'Menunggu', 
                'Disetujui', 
                'Ditolak', 
                'Dipinjam', 
                'Dikembalikan'
            ])->default('Menunggu');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowing_details');
    }
};