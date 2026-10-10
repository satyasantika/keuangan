<?php

namespace App\Actions\Pengguna;

use App\Models\User;
use App\Support\Impersonasi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * Kembali ke akun super-admin asal.
 */
class AkhiriImpersonasi
{
    public function handle(): User
    {
        $asal = Impersonasi::asal();

        if (! $asal instanceof User || ! $asal->aktif) {
            throw new AuthorizationException('Tidak ada impersonasi yang dapat diakhiri.');
        }

        $disamar = Auth::guard('web')->user();

        activity('users')
            ->performedOn($disamar instanceof User ? $disamar : $asal)
            ->causedBy($asal)
            ->event('impersonasi-akhir')
            ->log('Selesai menyamar'.($disamar instanceof User ? " sebagai {$disamar->email}" : ''));

        request()->session()->forget(Impersonasi::KUNCI);
        Auth::guard('web')->login($asal);
        request()->session()->put('password_hash_web', $asal->getAuthPassword());

        return $asal;
    }
}
