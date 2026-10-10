<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekap_honor_eksternal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_impor')->index();
            $table->string('sumber', 30)->default('fkipapp');
            $table->foreignUuid('tahun_anggaran_id')->constrained('tahun_anggaran')->restrictOnDelete();
            $table->foreignUuid('periode_id')->constrained('periode')->restrictOnDelete();
            $table->foreignUuid('unit_id')->nullable()->constrained('unit')->restrictOnDelete();
            $table->string('jenis_ujian', 30);
            $table->unsignedInteger('jumlah_kegiatan')->default(0);
            $table->decimal('jumlah_bruto', 15, 2)->default(0);
            $table->decimal('jumlah_pajak', 15, 2)->default(0);
            $table->decimal('jumlah_neto', 15, 2)->default(0);
            $table->string('nama_berkas_sumber', 255)->nullable();
            $table->string('status', 20)->default('aktif');
            $table->foreignUuid('diimpor_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['periode_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_honor_eksternal');
    }
};
