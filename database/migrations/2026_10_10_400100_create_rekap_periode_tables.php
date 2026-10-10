<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekap_periode', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('periode_id')->constrained('periode')->restrictOnDelete();
            $table->foreignUuid('unit_id')->constrained('unit')->restrictOnDelete();
            $table->unsignedSmallInteger('versi_kunci')->default(1);
            $table->decimal('pagu', 15, 2)->default(0);
            $table->decimal('total_disetujui_kumulatif', 15, 2)->default(0);
            $table->decimal('total_pencairan', 15, 2)->default(0);
            $table->decimal('total_realisasi', 15, 2)->default(0);
            $table->decimal('total_pajak', 15, 2)->default(0);
            $table->decimal('total_pengembalian', 15, 2)->default(0);
            $table->foreignUuid('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['periode_id', 'unit_id']);
        });

        Schema::create('rekap_periode_akun', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rekap_periode_id')->constrained('rekap_periode')->cascadeOnDelete();
            $table->foreignUuid('akun_id')->constrained('akun')->restrictOnDelete();
            $table->decimal('total_disetujui_kumulatif', 15, 2)->default(0);
            $table->decimal('total_realisasi', 15, 2)->default(0);

            $table->unique(['rekap_periode_id', 'akun_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_periode_akun');
        Schema::dropIfExists('rekap_periode');
    }
};
