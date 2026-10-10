<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pengajuan_id')->constrained('pengajuan')->restrictOnDelete();
            $table->unsignedSmallInteger('nomor_revisi')->default(0);
            $table->string('status', 30)->default('draf');
            $table->decimal('total', 15, 2)->default(0);
            $table->text('alasan_revisi')->nullable();
            $table->foreignUuid('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->dateTime('diajukan_pada')->nullable();
            $table->foreignUuid('disetujui_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('disetujui_pada')->nullable();
            $table->timestamps();

            $table->unique(['pengajuan_id', 'nomor_revisi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab');
    }
};
