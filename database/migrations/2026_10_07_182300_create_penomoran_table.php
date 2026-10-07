<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penomoran', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('jenis', 30);
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('nomor_terakhir')->default(0);
            $table->timestamps();

            $table->unique(['jenis', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penomoran');
    }
};
