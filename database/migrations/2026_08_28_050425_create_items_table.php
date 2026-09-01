<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('kode_barang', 50)->unique();
            $table->string('nama_barang');
            $table->
            enum('kategori', [
                        'Elektronik',
                        'Olahraga',
                        'Laboratorium',
                        'Buku',
                        'Furniture',
                        'Alat Kantor',
                        'Kesenian',
                        'Kebersihan',
                        'Kesehatan',
                        'Multimedia',
                        'Lainnya',
                ])->default('Lainnya');
            $table->integer('jumlah_total')->default(1);
            $table->text('deskripsi')->nullable();
            $table->boolean('bisa_dipinjam')->default(true);
            $table->string('icon', 100)->nullable()->default('fa-solid fa-box');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};