<?php

namespace App\Actions\Pengguna;

use App\Enums\Peran;
use App\Models\Unit;
use App\Models\User;
use App\Rules\EmailUnsil;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Impor massal pengguna dari teks hasil salin-tempel (mis. dari Excel/Google Sheets).
 *
 * Kolom berurutan: nama, surel, peran, kode unit, NIP, no. HP (kolom 4-6 boleh kosong/dihilangkan).
 * Pemisah otomatis: tab (hasil salin dari spreadsheet), titik koma, atau koma. Baris judul dilewati.
 * Semua-atau-tidak-sama-sekali: satu baris salah membatalkan seluruh batch dan semua galat dikembalikan.
 */
class ImporPenggunaTempel
{
    public const MAKS_BARIS = 500;

    /**
     * @return array{dibuat: int, galat: list<string>}
     */
    public function handle(string $teks, string $kataSandi, bool $wajibGantiSandi, User $oleh): array
    {
        if (! $oleh->can('pengguna.buat')) {
            throw new AuthorizationException('Anda tidak berhak menambah pengguna.');
        }

        [$baris, $galat] = $this->urai($teks);

        if ($baris === [] && $galat === []) {
            return ['dibuat' => 0, 'galat' => ['Tidak ada baris data untuk diimpor.']];
        }

        if (count($baris) > self::MAKS_BARIS) {
            return ['dibuat' => 0, 'galat' => ['Maksimal '.self::MAKS_BARIS.' baris per impor.']];
        }

        if (mb_strlen($kataSandi) < 8) {
            return ['dibuat' => 0, 'galat' => ['Kata sandi awal minimal 8 karakter.']];
        }

        $unit = Unit::query()->pluck('id', 'kode')->mapWithKeys(fn ($id, $kode) => [mb_strtolower((string) $kode) => $id]);
        $sudahAda = User::query()->pluck('email')->map(fn ($e) => mb_strtolower((string) $e))->flip();
        $terlihat = [];
        $siap = [];

        foreach ($baris as $nomor => $kolom) {
            [$nama, $surel, $peranTeks, $kodeUnit, $nip, $noHp] = $kolom;
            $surel = mb_strtolower($surel);
            $peran = $this->peran($peranTeks);

            $pesan = [];
            $validator = Validator::make(
                ['nama' => $nama, 'surel' => $surel],
                ['nama' => ['required', 'max:150'], 'surel' => ['required', 'email', 'max:255', new EmailUnsil]],
                [],
                ['nama' => 'nama', 'surel' => 'surel'],
            );
            array_push($pesan, ...$validator->errors()->all());

            if ($sudahAda->has($surel) || isset($terlihat[$surel])) {
                $pesan[] = "surel {$surel} sudah terdaftar/duplikat";
            }
            $terlihat[$surel] = true;

            if ($peran === null) {
                $pesan[] = "peran '{$peranTeks}' tidak dikenal";
            } elseif ($peran === Peran::SuperAdmin && ! $oleh->hasRole(Peran::SuperAdmin->value)) {
                $pesan[] = 'hanya super-admin yang boleh memberi peran super-admin';
            }

            $unitId = null;
            if ($kodeUnit !== '') {
                $unitId = $unit->get(mb_strtolower($kodeUnit));
                if ($unitId === null) {
                    $pesan[] = "kode unit '{$kodeUnit}' tidak ditemukan";
                }
            } elseif ($peran !== null && in_array($peran, Peran::wajibUnit(), true)) {
                $pesan[] = 'peran '.$peran->value.' wajib punya kode unit';
            }

            if (mb_strlen($nip) > 30 || mb_strlen($noHp) > 20) {
                $pesan[] = 'NIP maks. 30 dan no. HP maks. 20 karakter';
            }

            if ($pesan !== []) {
                $galat[] = "Baris {$nomor}: ".implode('; ', $pesan).'.';

                continue;
            }

            $siap[] = ['name' => $nama, 'email' => $surel, 'peran' => $peran, 'unit_id' => $unitId, 'nip' => $nip ?: null, 'no_hp' => $noHp ?: null];
        }

        if ($galat !== []) {
            return ['dibuat' => 0, 'galat' => $galat];
        }

        DB::transaction(function () use ($siap, $kataSandi, $wajibGantiSandi, $oleh): void {
            foreach ($siap as $data) {
                $peran = $data['peran'];
                unset($data['peran']);

                $akun = new User($data);
                $akun->password = $kataSandi;
                $akun->aktif = true;
                $akun->wajib_ganti_sandi = $wajibGantiSandi;
                $akun->email_verified_at = now();
                $akun->save();
                $akun->assignRole($peran->value);
            }

            activity('users')
                ->causedBy($oleh)
                ->event('impor-pengguna')
                ->withProperties(['jumlah' => count($siap)])
                ->log('Impor massal pengguna ('.count($siap).')');
        });

        return ['dibuat' => count($siap), 'galat' => []];
    }

    /**
     * @return array{0: array<int, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>, 1: list<string>}
     */
    private function urai(string $teks): array
    {
        $hasil = [];
        $galat = [];

        foreach (preg_split('/\R/u', trim($teks)) ?: [] as $i => $baris) {
            $nomor = $i + 1;

            if (trim($baris) === '') {
                continue;
            }

            $pemisah = str_contains($baris, "\t") ? "\t" : (str_contains($baris, ';') ? ';' : ',');
            $kolom = array_map(fn (?string $k): string => trim((string) $k), str_getcsv($baris, $pemisah, '"', ''));

            if (count($hasil) === 0 && $galat === [] && ! str_contains($kolom[1] ?? '', '@')) {
                continue; // baris judul
            }

            if (count($kolom) < 3) {
                $galat[] = "Baris {$nomor}: minimal berisi nama, surel, dan peran.";

                continue;
            }

            $hasil[$nomor] = array_slice(array_pad($kolom, 6, ''), 0, 6);
        }

        return [$hasil, $galat];
    }

    private function peran(string $teks): ?Peran
    {
        $kunci = mb_strtolower(trim($teks));

        foreach (Peran::cases() as $peran) {
            if ($kunci === $peran->value || $kunci === mb_strtolower($peran->getLabel())) {
                return $peran;
            }
        }

        return null;
    }
}
