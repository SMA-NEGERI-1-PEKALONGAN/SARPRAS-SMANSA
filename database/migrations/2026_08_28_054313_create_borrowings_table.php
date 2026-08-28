<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowings', function (Blueprint $table) {
            $table->id();
            $table->string('kode_transaksi')->unique();
            
            // Siapa yang meminjam
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Siapa admin yang memproses (menyetujui/menolak)
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->text('tujuan');
            $table->dateTime('tanggal_mulai');
            $table->dateTime('tanggal_selesai');
            
            $table->enum('status', [
                'Menunggu', 
                'Disetujui', 
                'Ditolak', 
                'Berjalan', 
                'Selesai'
            ])->default('Menunggu');
            
            $table->text('catatan_admin')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowings');
    }
};