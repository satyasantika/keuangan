<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_status', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('model_type', 100);
            $table->uuid('model_id');
            $table->string('dari', 30)->nullable();
            $table->string('ke', 30);
            $table->string('tahap', 30)->nullable();
            $table->text('catatan')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['model_type', 'model_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_status');
    }
};
