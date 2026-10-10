<?php

namespace App\Actions\Pengguna;

use App\Enums\Peran;
use App\Models\User;
use App\Support\Impersonasi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * Keadaan darurat: super-admin masuk sebagai pengguna lain. Selalu tercatat di jejak audit (mulai dan akhir),
 * dan setiap entri audit selama menyamar memuat id super-admin asal.
 */
class MulaiImpersonasi
{
    public function handle(User $pelaku, User $sasaran): void
    {
        if (Impersonasi::aktif()) {
            throw new AuthorizationException('Akhiri impersonasi yang sedang berjalan sebelum menyamar sebagai pengguna lain.');
        }

        if (! $pelaku->hasRole(Peran::SuperAdmin->value)) {
            throw new AuthorizationException('Hanya super-admin yang boleh menyamar sebagai pengguna lain.');
        }

        if ($pelaku->is($sasaran)) {
            throw new AuthorizationException('Tidak dapat menyamar sebagai diri sendiri.');
        }

        if (! $sasaran->aktif) {
            throw new AuthorizationException('Akun sasaran tidak aktif.');
        }

        activity('users')
            ->performedOn($sasaran)
            ->causedBy($pelaku)
            ->event('impersonasi-mulai')
            ->log("Mulai menyamar sebagai {$sasaran->email}");

        Auth::guard('web')->login($sasaran);

        // Sesi lama pelaku tidak boleh dipakai ulang; hash sandi diselaraskan agar AuthenticateSession tidak mengeluarkan sesi.
        request()->session()->put([
            Impersonasi::KUNCI => $pelaku->getKey(),
            'password_hash_web' => $sasaran->getAuthPassword(),
        ]);
    }
}
