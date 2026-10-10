<?php

namespace App\Actions\Pengguna;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pengguna memperbarui profilnya sendiri; bila kata sandi diganti, kewajiban ganti sandi dicabut,
 * dan sesi lain milik akun ini ikut tidak berlaku (hash sandi dalam sesi tidak lagi cocok; token "ingat saya" diganti).
 */
class PerbaruiProfil
{
    /**
     * @param  array{name?: string, nip?: ?string, no_hp?: ?string, password?: ?string}  $data  `password` berupa kata sandi baru mentah atau sudah di-hash
     */
    public function handle(User $pengguna, array $data): User
    {
        return DB::transaction(function () use ($pengguna, $data): User {
            $pengguna->fill(array_intersect_key($data, array_flip(['name', 'nip', 'no_hp'])));

            $sandiBaru = $data['password'] ?? null;
            $gantiSandi = filled($sandiBaru);

            if ($gantiSandi) {
                $pengguna->password = $sandiBaru;
                $pengguna->forceFill([
                    'wajib_ganti_sandi' => false,
                    'sandi_diubah_pada' => now(),
                ]);
                $pengguna->setRememberToken(Str::random(60));
            }

            $pengguna->save();

            if ($gantiSandi) {
                activity('users')
                    ->performedOn($pengguna)
                    ->causedBy($pengguna)
                    ->event('ganti-sandi')
                    ->log('Mengganti kata sandi');
            }

            return $pengguna;
        });
    }
}
