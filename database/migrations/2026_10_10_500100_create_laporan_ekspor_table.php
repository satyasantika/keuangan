<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_ekspor', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('jenis', 30);
            $table->json('parameter');
            $table->string('format', 5);
            $table->string('status', 20)->default('antre')->index();
            $table->string('path', 255)->nullable();
            $table->unsignedInteger('ukuran_byte')->nullable();
            $table->foreignUuid('diminta_oleh')->index()->constrained('users')->restrictOnDelete();
            $table->dateTime('mulai_pada')->nullable();
            $table->dateTime('selesai_pada')->nullable();
            $table->dateTime('kedaluwarsa_pada')->nullable();
            $table->text('pesan_galat')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_ekspor');
    }
};
