<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Impersonasi;
use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang ditandai `wajib_ganti_sandi` hanya boleh membuka halaman profil (atau keluar) sampai sandinya diganti.
 */
class WajibGantiSandi
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        if (! $pengguna instanceof User || ! $pengguna->wajib_ganti_sandi || Impersonasi::aktif()) {
            return $next($request);
        }

        $panel = Filament::getCurrentOrDefaultPanel();
        $diizinkan = [$panel->generateRouteName('auth.profile'), $panel->generateRouteName('auth.logout')];

        if ($request->routeIs($diizinkan) || $request->routeIs('livewire.*')) {
            return $next($request);
        }

        Notification::make()
            ->title('Ganti kata sandi Anda')
            ->body('Administrator mewajibkan Anda mengganti kata sandi sebelum melanjutkan.')
            ->warning()
            ->persistent()
            ->send();

        return redirect()->to(Filament::getProfileUrl());
    }
}
