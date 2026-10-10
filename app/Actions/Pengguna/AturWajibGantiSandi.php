<?php

namespace App\Actions\Pengguna;

use App\Enums\Peran;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Super-admin mewajibkan (atau mencabut kewajiban) pengguna mengganti kata sandi pada login berikutnya.
 */
class AturWajibGantiSandi
{
    /**
     * @param  iterable<User>  $pengguna
     */
    public function handle(iterable $pengguna, bool $wajib, User $oleh): int
    {
        if (! $oleh->hasRole(Peran::SuperAdmin->value)) {
            throw new AuthorizationException('Hanya super-admin yang boleh mengatur kewajiban ganti kata sandi.');
        }

        $daftar = Collection::make($pengguna);

        DB::transaction(function () use ($daftar, $wajib, $oleh): void {
            foreach ($daftar as $akun) {
                $akun->forceFill(['wajib_ganti_sandi' => $wajib])->save();

                activity('users')
                    ->performedOn($akun)
                    ->causedBy($oleh)
                    ->event($wajib ? 'wajib-ganti-sandi' : 'cabut-wajib-ganti-sandi')
                    ->log($wajib ? 'Mewajibkan ganti kata sandi' : 'Mencabut kewajiban ganti kata sandi');
            }
        });

        return $daftar->count();
    }
}
