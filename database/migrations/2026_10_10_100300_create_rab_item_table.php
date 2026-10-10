<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // cascadeOnDelete hanya terpakai saat RAB draf dihapus (guard model menolak selain draf).
            $table->foreignUuid('rab_id')->constrained('rab')->cascadeOnDelete();
            $table->foreignUuid('akun_id')->constrained('akun')->restrictOnDelete();
            $table->foreignUuid('asal_rab_item_id')->nullable()->constrained('rab_item')->restrictOnDelete();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->string('uraian', 255);
            $table->string('rincian_volume', 100)->nullable();
            $table->decimal('volume', 12, 2);
            $table->string('satuan', 30);
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('jumlah', 15, 2)->default(0);
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();

            $table->index(['rab_id', 'akun_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab_item');
    }
};
