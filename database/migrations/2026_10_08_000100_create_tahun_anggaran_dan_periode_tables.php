<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahun_anggaran', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('tahun')->unique();
            $table->string('nama', 50);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('status', 20)->default('perencanaan')->index();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('periode', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tahun_anggaran_id')->constrained('tahun_anggaran')->restrictOnDelete();
            $table->unsignedTinyInteger('bulan');
            $table->string('nama', 30);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('status', 20)->default('terbuka');
            $table->foreignUuid('dikunci_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('dikunci_pada')->nullable();
            $table->unsignedSmallInteger('versi_kunci')->default(0);
            $table->timestamps();

            $table->unique(['tahun_anggaran_id', 'bulan']);
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode');
        Schema::dropIfExists('tahun_anggaran');
    }
};
