<?php

namespace App\Actions\Spj;

use App\Enums\JenisTautan;
use App\Enums\StatusPencairan;
use App\Models\Pencairan;
use App\Models\RealisasiItem;
use App\Models\RiwayatStatus;
use App\Models\Spj;
use App\Models\TautanBerkas;
use App\Models\TautanBerkasBeku;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Snapshot tautan bukti (URL + waktu) saat SPJ diterima/pengembalian diverifikasi (BR-17).
 * Idempoten per (riwayat_status_id, tautan_berkas_id).
 */
class BekukanTautanSpj
{
    /**
     * Membekukan tautan realisasi SPJ, pencairan aktif, dan TOR/surat tugas pengajuan.
     */
    public function handle(Spj $spj, RiwayatStatus $riwayat): int
    {
        $pengajuan = $spj->pengajuan()->firstOrFail();

        $tautan = TautanBerkas::query()
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('pemilik_type', (new RealisasiItem)->getMorphClass())
                    ->whereIn('pemilik_id', RealisasiItem::query()->where('spj_id', $spj->id)->select('id')))
                ->orWhere(fn ($w) => $w->where('pemilik_type', (new Pencairan)->getMorphClass())
                    ->whereIn('pemilik_id', Pencairan::query()->where('pengajuan_id', $pengajuan->id)->where('status', StatusPencairan::Dicatat->value)->select('id')))
                ->orWhere(fn ($w) => $w->where('pemilik_type', $pengajuan->getMorphClass())
                    ->where('pemilik_id', $pengajuan->id)
                    ->whereIn('jenis', [JenisTautan::Tor->value, JenisTautan::SuratTugas->value])))
            ->get();

        return $this->salin($spj, $riwayat, $tautan);
    }

    /**
     * Membekukan tautan satu pemilik (mis. bukti setor pengembalian) dengan riwayat keputusan.
     */
    public function bekukanPemilik(Spj $spj, Model $pemilik, RiwayatStatus $riwayat): int
    {
        $tautan = TautanBerkas::query()
            ->where('pemilik_type', $pemilik->getMorphClass())
            ->where('pemilik_id', $pemilik->getKey())
            ->get();

        return $this->salin($spj, $riwayat, $tautan);
    }

    /**
     * @param  Collection<int, TautanBerkas>  $tautan
     */
    private function salin(Spj $spj, RiwayatStatus $riwayat, Collection $tautan): int
    {
        $sudah = TautanBerkasBeku::query()->where('riwayat_status_id', $riwayat->id)->pluck('tautan_berkas_id')->all();
        $jumlah = 0;

        foreach ($tautan as $t) {
            if (in_array($t->id, $sudah, true)) {
                continue;
            }

            TautanBerkasBeku::create([
                'spj_id' => $spj->id,
                'riwayat_status_id' => $riwayat->id,
                'tautan_berkas_id' => $t->id,
                'pemilik_type' => $t->pemilik_type,
                'pemilik_id' => $t->pemilik_id,
                'jenis' => $t->jenis->value,
                'label' => $t->label,
                'url' => $t->url,
                'drive_file_id' => $t->drive_file_id,
                'status_cek' => $t->status_cek->value,
                'dicek_pada' => $t->dicek_pada,
                'dibekukan_pada' => now(),
            ]);
            $jumlah++;
        }

        return $jumlah;
    }
}
