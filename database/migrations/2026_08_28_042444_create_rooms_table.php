<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('kode_ruangan')->unique(); // Contoh: LAB-KOM-1, AULA-01
            $table->string('nama_ruangan'); // Contoh: Lab Komputer 1
            $table->enum('tipe', ['Laboratorium', 'Aula', 'Ruang Rapat', 'Fasilitas Olahraga', 'Lainnya']);
            $table->integer('kapasitas')->nullable(); // Jumlah maksimal orang
            $table->text('fasilitas')->nullable(); // Bisa diisi: "AC, Proyektor, 40 PC"
            $table->boolean('status_tersedia')->default(true); // true = bisa dipinjam, false = sedang rusak/renovasi
            
            // Menggunakan icon (misal class dari Tabler Icons: 'ti-device-desktop')
            $table->string('icon')->nullable()->default('ti-door'); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};