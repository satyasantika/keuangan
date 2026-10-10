<?php

namespace App\Rules;

use App\Services\Berkas\PenguraiTautanDrive;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Tautan berkas sah (BR-15): https, host daftar putih, bukan pemendek URL, bukan folder Drive.
 */
class TautanBerkasValid implements ValidationRule
{
    public const PESAN = 'Gunakan tautan https Google Drive/Docs atau *.unsil.ac.id ke satu berkas (bukan folder/pemendek URL).';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::sah($value)) {
            $fail(self::PESAN);
        }
    }

    public static function sah(mixed $url): bool
    {
        if (! is_string($url) || $url === '' || strlen($url) > (int) config('berkas.panjang_maks', 2048)) {
            return false;
        }

        $bagian = parse_url($url);

        if ($bagian === false || strtolower($bagian['scheme'] ?? '') !== 'https' || isset($bagian['user']) || isset($bagian['pass']) || isset($bagian['port'])) {
            return false;
        }

        $host = strtolower($bagian['host'] ?? '');

        if ($host === '' || in_array($host, (array) config('berkas.pemendek_ditolak', []), true) || ! self::hostDiizinkan($host)) {
            return false;
        }

        return ! app(PenguraiTautanDrive::class)->urai($url)['folder'];
    }

    private static function hostDiizinkan(string $host): bool
    {
        foreach ((array) config('berkas.domain_diizinkan', []) as $pola) {
            $pola = strtolower((string) $pola);

            if (str_starts_with($pola, '*.')) {
                $induk = substr($pola, 2);

                if ($host === $induk || str_ends_with($host, '.'.$induk)) {
                    return true;
                }
            } elseif ($host === $pola) {
                return true;
            }
        }

        return false;
    }
}
