<?php

namespace App\Contracts;

use App\Enums\JenisTautan;
use App\Enums\StatusCekTautan;
use App\Models\TautanBerkas;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu-satunya pintu akses berkas. Implementasi aktif: TautanEksternal.
 */
interface PenyimpananBerkas
{
    public function simpan(Model&PemilikTautan $pemilik, JenisTautan $jenis, string $url, string $label, bool $aksesTerbatasDikonfirmasi, User $oleh): TautanBerkas;

    public function hapus(TautanBerkas $tautan, User $oleh): void;

    public function urlTampil(TautanBerkas $tautan): string;

    public function urlPratinjau(TautanBerkas $tautan): ?string;

    public function periksa(TautanBerkas $tautan): StatusCekTautan;
}
