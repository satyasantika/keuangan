<?php

namespace App\Support;

use App\Models\Pengaturan as BarisPengaturan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Akses pengaturan kebijakan dengan cast sesuai kolom tipe (string, int, bool, json).
 * Seluruh pengaturan tercache 24 jam di 'pengaturan:semua'.
 */
final class Pengaturan
{
    public const KUNCI_CACHE = 'pengaturan:semua';

    public static function get(string $kunci, mixed $bawaan = null): mixed
    {
        $semua = self::semua();

        if (! array_key_exists($kunci, $semua)) {
            return $bawaan;
        }

        return self::cast($semua[$kunci]['nilai'], $semua[$kunci]['tipe']) ?? $bawaan;
    }

    public static function set(string $kunci, mixed $nilai, User $oleh): void
    {
        $baris = BarisPengaturan::query()->firstOrNew(['kunci' => $kunci]);

        if (! $baris->exists) {
            $baris->tipe = match (true) {
                is_bool($nilai) => 'bool',
                is_int($nilai) => 'int',
                is_array($nilai) => 'json',
                default => 'string',
            };
        }

        $lama = $baris->exists ? self::cast($baris->nilai, $baris->tipe) : null;
        $baris->nilai = self::simpanSebagai($nilai, $baris->tipe);

        if ($baris->isDirty()) {
            $baris->save();

            activity('pengaturan')
                ->performedOn($baris)
                ->causedBy($oleh)
                ->event('updated')
                ->withProperties(['kunci' => $kunci, 'old' => $lama, 'attributes' => self::cast($baris->nilai, $baris->tipe)])
                ->log("Pengaturan {$kunci} diubah");
        }

        Cache::forget(self::KUNCI_CACHE);
    }

    /**
     * @return array<string, array{nilai: string|null, tipe: string}>
     */
    public static function semua(): array
    {
        return Cache::remember(self::KUNCI_CACHE, now()->addDay(), fn (): array => BarisPengaturan::query()
            ->get(['kunci', 'nilai', 'tipe'])
            ->mapWithKeys(fn (BarisPengaturan $b) => [$b->kunci => ['nilai' => $b->nilai, 'tipe' => $b->tipe]])
            ->all());
    }

    public static function cast(?string $nilai, string $tipe): mixed
    {
        if ($nilai === null) {
            return null;
        }

        return match ($tipe) {
            'int' => (int) $nilai,
            'bool' => filter_var($nilai, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($nilai, true),
            default => $nilai,
        };
    }

    private static function simpanSebagai(mixed $nilai, string $tipe): ?string
    {
        if ($nilai === null) {
            return null;
        }

        return match ($tipe) {
            'bool' => filter_var($nilai, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'int' => (string) (int) $nilai,
            'json' => json_encode($nilai, JSON_THROW_ON_ERROR),
            default => (string) $nilai,
        };
    }
}
