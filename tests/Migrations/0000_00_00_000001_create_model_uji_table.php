<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel khusus uji untuk trait lintas model (hanya dimuat TestCase; bukan migrasi aplikasi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_uji', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('tanggal');
            $table->uuid('periode_id')->nullable();
            $table->string('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_uji');
    }
};
