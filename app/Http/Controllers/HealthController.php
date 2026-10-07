<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    /**
     * Endpoint kesehatan untuk pemantauan dan deploy (STANDAR-TEKNIS §6).
     * Tidak membocorkan pesan exception.
     */
    public function __invoke(): JsonResponse
    {
        $db = $this->periksa(fn () => DB::select('select 1'));
        $redis = $this->periksa(fn () => Redis::connection()->ping());

        $sehat = $db && $redis;

        return response()->json([
            'status' => $sehat ? 'ok' : 'gagal',
            'db' => $db,
            'redis' => $redis,
            'versi' => config('app.version'),
            'waktu' => now()->toIso8601String(),
        ], $sehat ? 200 : 503);
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
