<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Impersonasi;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Peran keuangan (super-admin, admin-keuangan, bpp, wadek-keuangan) wajib mengaktifkan MFA;
 * selama belum, semua halaman panel dialihkan ke halaman pengaturan MFA (F2.6).
 */
class WajibMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        if (! Impersonasi::aktif() && $pengguna instanceof User && $pengguna->wajibMfa() && ! $pengguna->sudahAktifkanMfa()) {
            return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
        }

        return $next($request);
    }
}
