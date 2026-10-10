<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spj', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pengajuan_id')->unique()->constrained('pengajuan')->restrictOnDelete();
            $table->string('nomor', 40)->nullable()->unique();
            $table->string('status', 20)->default('draf')->index();
            $table->decimal('total_realisasi', 15, 2)->default(0);
            $table->decimal('total_pajak', 15, 2)->default(0);
            $table->foreignUuid('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->dateTime('diajukan_pada')->nullable();
            $table->foreignUuid('pernyataan_final_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('pernyataan_final_pada')->nullable();
            $table->foreignUuid('diputuskan_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('diputuskan_pada')->nullable();
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamps();
        });

        Schema::create('realisasi_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('spj_id')->constrained('spj')->restrictOnDelete();
            $table->foreignUuid('rab_item_id')->constrained('rab_item')->restrictOnDelete();
            $table->foreignUuid('periode_id')->constrained('periode')->restrictOnDelete();
            $table->date('tanggal_bukti');
            $table->string('nomor_bukti', 100);
            $table->string('uraian', 255);
            $table->string('penerima', 150)->nullable();
            $table->decimal('volume', 12, 2);
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('jumlah_bruto', 15, 2);
            $table->decimal('total_pajak', 15, 2)->default(0);
            $table->decimal('jumlah_neto', 15, 2);
            $table->boolean('indikasi_bukti_ganda')->default(false);
            $table->foreignUuid('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('spj_id');
            $table->index('rab_item_id');
            $table->index('periode_id');
        });

        Schema::create('potongan_pajak', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('realisasi_item_id')->constrained('realisasi_item')->cascadeOnDelete();
            $table->foreignUuid('jenis_pajak_id')->constrained('jenis_pajak')->restrictOnDelete();
            $table->foreignUuid('tarif_pajak_id')->nullable()->constrained('tarif_pajak')->restrictOnDelete();
            $table->decimal('tarif_referensi', 5, 2)->nullable();
            $table->decimal('dasar_pengenaan', 15, 2);
            $table->decimal('nilai', 15, 2);
            $table->string('nomor_bukti_setor', 100)->nullable();
            $table->date('tanggal_setor')->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->foreignUuid('dicatat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('realisasi_item_id');
            $table->index('jenis_pajak_id');
        });

        Schema::create('pengembalian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pengajuan_id')->constrained('pengajuan')->restrictOnDelete();
            $table->foreignUuid('periode_id')->constrained('periode')->restrictOnDelete();
            $table->date('tanggal_setor');
            $table->decimal('jumlah', 15, 2);
            $table->string('nomor_bukti_setor', 100);
            $table->string('keterangan', 255)->nullable();
            $table->string('status', 20)->default('diajukan');
            $table->foreignUuid('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('diputuskan_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('diputuskan_pada')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['pengajuan_id', 'status']);
        });

        Schema::create('tautan_berkas_beku', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('spj_id')->constrained('spj')->restrictOnDelete();
            $table->foreignUuid('riwayat_status_id')->constrained('riwayat_status')->restrictOnDelete();
            $table->foreignUuid('tautan_berkas_id')->constrained('tautan_berkas')->restrictOnDelete();
            $table->string('pemilik_type', 100);
            $table->uuid('pemilik_id');
            $table->string('jenis', 30);
            $table->string('label', 150);
            $table->string('url', 2048);
            $table->string('drive_file_id', 100)->nullable();
            $table->string('status_cek', 30);
            $table->dateTime('dicek_pada')->nullable();
            $table->dateTime('dibekukan_pada');
            $table->timestamp('created_at')->nullable();

            $table->index('spj_id');
            $table->unique(['riwayat_status_id', 'tautan_berkas_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tautan_berkas_beku');
        Schema::dropIfExists('pengembalian');
        Schema::dropIfExists('potongan_pajak');
        Schema::dropIfExists('realisasi_item');
        Schema::dropIfExists('spj');
    }
};
