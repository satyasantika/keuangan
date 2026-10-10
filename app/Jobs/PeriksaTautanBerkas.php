<?php

namespace App\Jobs;

use App\Contracts\PenyimpananBerkas;
use App\Enums\Peran;
use App\Enums\StatusCekTautan;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Notifications\TautanBerkasBermasalah;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Memeriksa keteraksesan tautan (BR-15) dan memberi tahu bila tautan mati atau bukti keuangan terbuka publik.
 */
class PeriksaTautanBerkas implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public TautanBerkas $tautan)
    {
        $this->onQueue('default');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('periksa-tautan')];
    }

    public function handle(PenyimpananBerkas $penyimpanan): void
    {
        $tautan = $this->tautan->fresh();

        if ($tautan === null) {
            return;
        }

        $statusLama = $tautan->status_cek;
        $privasiLama = $tautan->peringatan_privasi;

        $status = $penyimpanan->periksa($tautan);

        $alasan = match (true) {
            $status === StatusCekTautan::TidakDapatDiakses && $statusLama !== StatusCekTautan::TidakDapatDiakses => TautanBerkasBermasalah::TIDAK_DAPAT_DIAKSES,
            $tautan->peringatan_privasi && ! $privasiLama => TautanBerkasBermasalah::AKSES_PUBLIK,
            default => null,
        };

        if ($alasan !== null) {
            Notification::send($this->penerima($tautan), new TautanBerkasBermasalah($tautan, $alasan));
        }
    }

    /**
     * Penambah tautan + admin-unit unit pemilik.
     *
     * @return Collection<int, User>
     */
    private function penerima(TautanBerkas $tautan): Collection
    {
        $penerima = User::query()->whereKey($tautan->ditambahkan_oleh)->get();
        $unitId = $tautan->pemilik?->getAttribute('unit_id');

        if ($unitId !== null) {
            $penerima = $penerima->merge(
                User::query()->where('unit_id', $unitId)->where('aktif', true)->role(Peran::AdminUnit->value)->get(),
            );
        }

        return $penerima->unique('id')->values();
    }
}
