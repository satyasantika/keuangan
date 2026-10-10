<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagu', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tahun_anggaran_id')->constrained('tahun_anggaran')->restrictOnDelete();
            $table->foreignUuid('unit_id')->constrained('unit')->restrictOnDelete();
            $table->decimal('jumlah', 15, 2)->default(0);
            $table->string('sumber_dana', 100)->nullable();
            $table->string('status', 20)->default('draf');
            $table->text('keterangan')->nullable();
            $table->foreignUuid('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('ditetapkan_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('ditetapkan_pada')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tahun_anggaran_id', 'unit_id']);
        });

        Schema::create('pagu_akun', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pagu_id')->constrained('pagu')->cascadeOnDelete();
            $table->foreignUuid('akun_id')->constrained('akun')->restrictOnDelete();
            $table->decimal('jumlah', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['pagu_id', 'akun_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagu_akun');
        Schema::dropIfExists('pagu');
    }
};
