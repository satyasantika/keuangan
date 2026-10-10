<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 20)->unique();
            $table->string('nama', 150);
            $table->string('jenis', 30)->default('prodi')->index();
            $table->string('jenjang', 10)->nullable();
            $table->foreignUuid('kepala_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kode_eksternal', 50)->nullable()->index();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit');
    }
};
