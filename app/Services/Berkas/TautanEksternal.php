<?php

namespace App\Services\Berkas;

use App\Contracts\PemilikTautan;
use App\Contracts\PenyimpananBerkas;
use App\Enums\JenisTautan;
use App\Enums\StatusCekTautan;
use App\Models\TautanBerkas;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Implementasi aktif BERKAS_MODE=tautan: server hanya menyimpan URL & metadata.
 */
class TautanEksternal implements PenyimpananBerkas
{
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

    public function urlPratinjau(TautanBerkas $tautan): ?string
    {
        // Dilengkapi F4.5.
        return null;
    }

    public function periksa(TautanBerkas $tautan): StatusCekTautan
    {
        throw new LogicException('Pemeriksaan tautan dilengkapi pada F4.5.');
    }
}
