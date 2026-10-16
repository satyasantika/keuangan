<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    private const DETAK_MAKS_MENIT = 5;

    /**
     * Endpoint kesehatan untuk pemantauan dan deploy (STANDAR-TEKNIS §1b, Profil Ringan).
     * 503 hanya bila DB gagal; antrean dan detak penjadwal bersifat informatif agar healthcheck
     * container tidak memicu restart. Tidak membocorkan pesan exception.
     */
    public function __invoke(): JsonResponse
    {
        $db = $this->periksa(fn () => DB::select('select 1'));

        return response()->json([
            'status' => $db ? 'ok' : 'gagal',
            'db' => $db ? 'ok' : 'gagal',
            'job_tertunda' => $this->hitung(fn () => DB::table('jobs')
                ->whereNull('reserved_at')
                ->where('available_at', '<=', now()->subMinutes(10)->getTimestamp())
                ->count()),
            'job_gagal_24j' => $this->hitung(fn () => DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count()),
            'detak_penjadwal' => $this->detakPenjadwal(),
            'versi' => config('app.version'),
            'waktu' => now()->toIso8601String(),
        ], $db ? 200 : 503);
    }

    private function detakPenjadwal(): string
    {
        try {
            $detak = Cache::get(config('app.kode').':detak-penjadwal');
        } catch (Throwable) {
            return 'gagal';
        }

        if (! is_int($detak)) {
            return 'belum-ada';
        }

        return (now()->getTimestamp() - $detak) > self::DETAK_MAKS_MENIT * 60 ? 'terlambat' : 'ok';
    }

    private function hitung(callable $hitung): int|string
    {
        try {
            return (int) $hitung();
        } catch (Throwable) {
            return 'gagal';
        }
    }

    private function periksa(callable $cek): bool
    {
        try {
            $cek();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
