<?php

namespace App\Actions\Berkas;

use App\Contracts\PemilikTautan;
use App\Contracts\PenyimpananBerkas;
use App\Enums\JenisTautan;
use App\Exceptions\KesalahanDomain;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Menyimpan tautan berkas tervalidasi (BR-15) lewat kontrak PenyimpananBerkas.
 */
class SimpanTautanBerkas
{
    public const PESAN_AKSES_TERBATAS = 'Bukti keuangan memuat NPWP/rekening — bagikan terbatas ke domain unsil.ac.id lalu centang konfirmasi.';

    public function __construct(private readonly PenyimpananBerkas $penyimpanan) {}

    public function handle(Model&PemilikTautan $pemilik, JenisTautan $jenis, string $url, string $label, bool $aksesTerbatasDikonfirmasi, User $oleh): TautanBerkas
    {
        if (! $oleh->can('create', [TautanBerkas::class, $pemilik])) {
            throw new AuthorizationException('Anda tidak berhak menambah tautan pada data ini.');
        }

        if (! $pemilik->tautanDapatDiubah()) {
            throw new KesalahanDomain('Tautan sudah terkunci.');
        }

        $url = trim($url);
        $label = trim($label);

        if (! TautanBerkasValid::sah($url)) {
            throw ValidationException::withMessages(['url' => TautanBerkasValid::PESAN]);
        }

        if ($label === '' || mb_strlen($label) > 150) {
            throw ValidationException::withMessages(['label' => 'Label wajib diisi (maks. 150 karakter).']);
        }

        if ($jenis->buktiKeuangan() && ! $aksesTerbatasDikonfirmasi) {
            throw ValidationException::withMessages(['akses_terbatas_dikonfirmasi' => self::PESAN_AKSES_TERBATAS]);
        }

        $kunci = "simpan-tautan:{$oleh->getKey()}";

        if (RateLimiter::tooManyAttempts($kunci, 30)) {
            throw new KesalahanDomain('Terlalu banyak tautan disimpan. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.');
        }

        RateLimiter::hit($kunci, 60);

        return $this->penyimpanan->simpan($pemilik, $jenis, $url, $label, $aksesTerbatasDikonfirmasi, $oleh);
    }
}
