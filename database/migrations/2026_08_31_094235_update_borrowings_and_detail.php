<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->longText('tanda_tangan')->nullable()->after('catatan_admin');
        });

        Schema::table('borrowing_details', function (Blueprint $table) {
            $table->json('fasilitas')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropColumn('tanda_tangan');
        });

        Schema::table('borrowing_details', function (Blueprint $table) {
            $table->dropColumn('fasilitas');
        });
    }
};