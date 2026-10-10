<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pencairan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pengajuan_id')->constrained('pengajuan')->restrictOnDelete();
            $table->foreignUuid('jenis_pencairan_id')->constrained('jenis_pencairan')->restrictOnDelete();
            $table->foreignUuid('periode_id')->constrained('periode')->restrictOnDelete();
            $table->date('tanggal');
            $table->decimal('jumlah', 15, 2);
            $table->string('nomor_bukti', 100);
            $table->string('penerima', 150)->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->string('status', 20)->default('dicatat');
            $table->foreignUuid('dicatat_oleh')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dibatalkan_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('dibatalkan_pada')->nullable();
            $table->text('alasan_batal')->nullable();
            $table->timestamps();

            $table->index(['pengajuan_id', 'status']);
            $table->index('periode_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pencairan');
    }
};
