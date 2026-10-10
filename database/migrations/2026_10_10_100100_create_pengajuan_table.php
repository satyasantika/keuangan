<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor', 40)->nullable()->unique();
            $table->foreignUuid('tahun_anggaran_id')->constrained('tahun_anggaran')->restrictOnDelete();
            $table->foreignUuid('unit_id')->constrained('unit')->restrictOnDelete();
            $table->foreignUuid('kategori_penggunaan_dana_id')->nullable()->constrained('kategori_penggunaan_dana')->restrictOnDelete();
            $table->string('judul_kegiatan', 255);
            $table->text('deskripsi')->nullable();
            $table->date('tanggal_mulai_kegiatan');
            $table->date('tanggal_selesai_kegiatan');
            $table->string('tempat', 255)->nullable();
            $table->string('penanggung_jawab', 150);
            // FK ke rab.id ditambahkan setelah tabel rab dibuat.
            $table->uuid('rab_aktif_id')->nullable();
            $table->string('status', 30)->default('draf')->index();
            $table->boolean('wajib_kepala_unit')->default(true);
            $table->boolean('wajib_wadek')->default(true);
            $table->decimal('total_diajukan', 15, 2)->default(0);
            $table->decimal('total_disetujui', 15, 2)->default(0);
            $table->decimal('total_dicairkan', 15, 2)->default(0);
            $table->decimal('total_realisasi', 15, 2)->default(0);
            $table->decimal('total_pajak', 15, 2)->default(0);
            $table->decimal('total_dikembalikan', 15, 2)->default(0);
            $table->date('batas_spj')->nullable()->index();
            $table->foreignUuid('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->dateTime('diajukan_pada')->nullable();
            $table->dateTime('disetujui_pada')->nullable();
            $table->dateTime('selesai_pada')->nullable();
            $table->text('alasan_batal')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tahun_anggaran_id', 'unit_id', 'status']);
            $table->fullText('judul_kegiatan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan');
    }
};
