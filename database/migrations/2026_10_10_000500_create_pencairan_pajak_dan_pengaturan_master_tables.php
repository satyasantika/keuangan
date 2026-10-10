<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_pencairan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 10)->unique();
            $table->string('nama', 100);
            $table->string('pola', 10)->default('semua');
            $table->string('keterangan', 255)->nullable();
            $table->boolean('perlu_verifikasi')->default(true);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('jenis_pajak', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tarif_pajak', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jenis_pajak_id')->constrained('jenis_pajak')->restrictOnDelete();
            $table->string('uraian', 255);
            $table->decimal('tarif_persen', 5, 2);
            $table->date('berlaku_mulai');
            $table->date('berlaku_sampai')->nullable();
            $table->string('dasar_hukum', 255)->nullable();
            $table->boolean('perlu_verifikasi')->default(true);
            $table->boolean('aktif')->default(true);
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();

            $table->index(['jenis_pajak_id', 'berlaku_mulai']);
        });

        Schema::create('pengaturan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kunci', 100)->unique();
            $table->text('nilai')->nullable();
            $table->string('tipe', 20)->default('string');
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
        Schema::dropIfExists('tarif_pajak');
        Schema::dropIfExists('jenis_pajak');
        Schema::dropIfExists('jenis_pencairan');
    }
};
