<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akun', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 10)->unique();
            $table->string('nama', 255);
            $table->string('kelompok', 10)->nullable()->index();
            $table->string('jenis_belanja', 30)->nullable();
            $table->string('sumber', 10)->default('impor');
            $table->boolean('aktif')->default(true);
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('kategori_penggunaan_dana', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 40)->unique();
            $table->string('nama', 150);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori_penggunaan_dana');
        Schema::dropIfExists('akun');
    }
};
