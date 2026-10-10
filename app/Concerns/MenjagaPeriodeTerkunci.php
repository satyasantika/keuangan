<?php

namespace App\Concerns;

use App\Enums\StatusPeriode;
use App\Enums\StatusTahunAnggaran;
use App\Exceptions\KesalahanDomain;
use App\Exceptions\PeriodeTerkunciException;
use App\Models\Periode;
use Illuminate\Support\Facades\Schema;

/**
 * Menolak simpan/hapus data transaksi yang tanggalnya (lama maupun baru) jatuh pada periode
 * terkunci atau tahun anggaran ditutup (BR-19, BR-20). Model mendefinisikan
 * kolomTanggalPeriode(), mis. ['tanggal'].
 */
trait MenjagaPeriodeTerkunci
{
    /**
     * @return array<int, string>
     */
    abstract protected function kolomTanggalPeriode(): array;

    protected static function bootMenjagaPeriodeTerkunci(): void
    {
        static::saving(function ($model): void {
            $model->pastikanPeriodeTerbuka(baru: true);
            $model->pastikanPeriodeTerbuka(baru: false);
            $model->isiPeriodeId();
        });

        static::deleting(function ($model): void {
            $model->pastikanPeriodeTerbuka(baru: false);
        });
    }

    private function pastikanPeriodeTerbuka(bool $baru): void
    {
        foreach ($this->kolomTanggalPeriode() as $kolom) {
            $nilai = $baru ? $this->getAttribute($kolom) : $this->getOriginal($kolom);

            if (blank($nilai)) {
                continue;
            }

            $periode = Periode::cariUntukTanggal($nilai);

            if ($periode === null) {
                if ($baru) {
                    Periode::untukTanggal($nilai); // melempar KesalahanDomain
                }

                continue;
            }

            if ($periode->status === StatusPeriode::Terkunci) {
                throw new PeriodeTerkunciException("Periode {$periode->nama} terkunci.");
            }

            if ($periode->tahunAnggaran->status === StatusTahunAnggaran::Ditutup) {
                throw new KesalahanDomain("Tahun anggaran {$periode->tahunAnggaran->tahun} sudah ditutup.");
            }
        }
    }

    private function isiPeriodeId(): void
    {
        $kolom = $this->kolomTanggalPeriode()[0] ?? null;

        if ($kolom === null || blank($this->getAttribute($kolom))) {
            return;
        }

        static $punyaKolom = [];

        if (! ($punyaKolom[$this->getTable()] ??= Schema::hasColumn($this->getTable(), 'periode_id'))) {
            return;
        }

        $this->setAttribute('periode_id', Periode::untukTanggal($this->getAttribute($kolom))->getKey());
    }
}
