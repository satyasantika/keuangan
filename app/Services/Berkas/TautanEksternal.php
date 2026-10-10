<?php

namespace App\Services\Berkas;

use App\Contracts\PemilikTautan;
use App\Contracts\PenyimpananBerkas;
use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\StatusCekTautan;
use App\Models\TautanBerkas;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Implementasi aktif BERKAS_MODE=tautan: server hanya menyimpan URL & metadata.
 */
class TautanEksternal implements PenyimpananBerkas
{
    private const MAKS_REDIRECT = 5;

    public function __construct(private readonly PenguraiTautanDrive $pengurai) {}

    public function simpan(Model&PemilikTautan $pemilik, JenisTautan $jenis, string $url, string $label, bool $aksesTerbatasDikonfirmasi, User $oleh): TautanBerkas
    {
        $urai = $this->pengurai->urai($url);

        return TautanBerkas::create([
            'pemilik_type' => $pemilik->getMorphClass(),
            'pemilik_id' => $pemilik->getKey(),
            'jenis' => $jenis,
            'label' => $label,
            'url' => $url,
            'penyedia' => $urai['penyedia'],
            'drive_file_id' => $urai['drive_file_id'],
            'akses_terbatas_dikonfirmasi' => $aksesTerbatasDikonfirmasi,
            'ditambahkan_oleh' => $oleh->getKey(),
        ]);
    }

    public function hapus(TautanBerkas $tautan, User $oleh): void
    {
        $tautan->delete();
    }

    public function urlTampil(TautanBerkas $tautan): string
    {
        return $tautan->url;
    }

    /**
     * Pratinjau sematan Drive: foto lewat lh3.googleusercontent.com, PDF/Docs lewat /preview.
     */
    public function urlPratinjau(TautanBerkas $tautan): ?string
    {
        if ($tautan->drive_file_id === null || ! in_array($tautan->penyedia, [PenyediaTautan::GoogleDrive, PenyediaTautan::GoogleDocs], true)) {
            return null;
        }

        if ($tautan->jenis === JenisTautan::FotoKegiatan) {
            return "https://lh3.googleusercontent.com/d/{$tautan->drive_file_id}";
        }

        return "https://drive.google.com/file/d/{$tautan->drive_file_id}/preview";
    }

    /**
     * Pemeriksaan keteraksesan tanpa cookie/kredensial dan tanpa mengunduh isi (HEAD, cadangan GET Range 0-0).
     * Mengisi status_cek, dicek_pada, dan peringatan_privasi. Hasil informatif, tidak memblokir apa pun.
     */
    public function periksa(TautanBerkas $tautan): StatusCekTautan
    {
        [$status, $publik] = $this->klasifikasi($tautan->url);

        $tautan->forceFill([
            'status_cek' => $status,
            'dicek_pada' => now(),
            'peringatan_privasi' => $publik && $tautan->jenis->buktiKeuangan(),
        ])->save();

        return $status;
    }

    /**
     * @return array{0: StatusCekTautan, 1: bool} [status, dapat dibuka anonim]
     */
    private function klasifikasi(string $url): array
    {
        try {
            for ($lompatan = 0; $lompatan <= self::MAKS_REDIRECT; $lompatan++) {
                $respons = $this->minta($url);

                if ($respons->redirect()) {
                    $tujuan = (string) $respons->header('Location');

                    if ($tujuan === '') {
                        return [StatusCekTautan::TidakDapatDiakses, false];
                    }

                    $url = $this->absolut($tujuan, $url);

                    if ($this->halamanLogin($url)) {
                        // Berkas ada dan aksesnya terbatas — kondisi yang diharapkan.
                        return [StatusCekTautan::DapatDiakses, false];
                    }

                    continue;
                }

                return match (true) {
                    in_array($respons->status(), [401, 403], true) => [StatusCekTautan::DapatDiakses, false],
                    $respons->successful() => [StatusCekTautan::DapatDiakses, true],
                    default => [StatusCekTautan::TidakDapatDiakses, false],
                };
            }

            return [StatusCekTautan::TidakDapatDiakses, false];
        } catch (Throwable) {
            return [StatusCekTautan::TidakDapatDiakses, false];
        }
    }

    private function minta(string $url): Response
    {
        $klien = Http::withOptions(['allow_redirects' => false, 'stream' => true, 'cookies' => false])
            ->timeout(10)
            ->withUserAgent('KeuanganFKIP-PemeriksaTautan/1.0');

        $respons = $klien->head($url);

        if (in_array($respons->status(), [405, 501], true)) {
            $respons = $klien->withHeaders(['Range' => 'bytes=0-0'])->get($url);
        }

        return $respons;
    }

    private function halamanLogin(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'accounts.google.com' || str_starts_with((string) parse_url($url, PHP_URL_PATH), '/ServiceLogin');
    }

    private function absolut(string $tujuan, string $asal): string
    {
        if (preg_match('#^https?://#i', $tujuan)) {
            return $tujuan;
        }

        $skema = parse_url($asal, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($asal, PHP_URL_HOST);

        return "{$skema}://{$host}/".ltrim($tujuan, '/');
    }
}
