<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran & bukti sebagai tautan (STANDAR-TEKNIS §1a, BR-15). Kolom penyedia/status_cek disimpan
 * VARCHAR dan di-cast ke Enum (konvensi status 03-SKEMA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tautan_berkas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('pemilik');
            $table->string('jenis', 30)->index();
            $table->string('label', 150);
            $table->string('url', 2048);
            $table->string('penyedia', 20);
            $table->string('drive_file_id', 100)->nullable()->index();
            $table->string('status_cek', 30)->default('belum')->index();
            $table->dateTime('dicek_pada')->nullable();
            $table->boolean('akses_terbatas_dikonfirmasi')->default(false);
            $table->boolean('peringatan_privasi')->default(false);
            $table->foreignUuid('ditambahkan_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tautan_berkas');
    }
};
