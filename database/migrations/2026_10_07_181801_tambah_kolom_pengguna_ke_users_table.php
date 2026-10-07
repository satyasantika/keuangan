<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip', 30)->nullable()->index()->after('password');
            $table->string('no_hp', 20)->nullable()->after('nip');
            $table->string('kode_eksternal', 50)->nullable()->index()->after('no_hp');
            $table->boolean('aktif')->default(true)->after('kode_eksternal');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nip', 'no_hp', 'kode_eksternal', 'aktif']);
        });
    }
};
