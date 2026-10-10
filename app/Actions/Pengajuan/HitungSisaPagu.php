<?php

namespace App\Actions\Pengajuan;

use App\Enums\StatusPengajuan;
use App\Models\Pagu;
use App\Models\PaguAkun;
use App\Models\Pengajuan;
use App\Models\RabItem;
use App\Support\Uang;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sisa pagu unit = pagu ditetapkan − Σ komitmen pengajuan (BR-04).
 */
class HitungSisaPagu
{
    /**
     * @return array{pagu: string, komitmen: string, sisa: string}|null null bila diminta level akun
     *                                                                  tetapi pagu tidak dirinci untuk akun itu
     */
    public function handle(string $tahunAnggaranId, string $unitId, ?string $akunId = null, ?string $kecualiPengajuanId = null): ?array
    {
        $pagu = Pagu::berlakuUntuk($tahunAnggaranId, $unitId);

        if ($akunId !== null) {
            $rincian = $pagu === null ? null : PaguAkun::query()->where('pagu_id', $pagu->id)->where('akun_id', $akunId)->first();

            if ($rincian === null) {
                return null;
            }

            return $this->susun((string) $rincian->jumlah, $this->komitmenAkun($tahunAnggaranId, $unitId, $akunId, $kecualiPengajuanId));
        }

        return $this->susun($pagu === null ? '0.00' : (string) $pagu->jumlah, $this->komitmenUnit($tahunAnggaranId, $unitId, $kecualiPengajuanId));
    }

    /**
     * @return array{pagu: string, komitmen: string, sisa: string}
     */
    private function susun(string $pagu, string $komitmen): array
    {
        return [
            'pagu' => Uang::jumlahkan([$pagu]),
            'komitmen' => $komitmen,
            'sisa' => Uang::kurang($pagu, $komitmen),
        ];
    }

    private function komitmenUnit(string $tahunAnggaranId, string $unitId, ?string $kecualiPengajuanId): string
    {
        $nilai = [];

        foreach ($this->pengajuanMengikat($tahunAnggaranId, $unitId, $kecualiPengajuanId)->get() as $pengajuan) {
            $kolom = $pengajuan->status->menghitungKomitmen();

            if ($kolom !== null) {
                $nilai[] = (string) $pengajuan->getAttribute($kolom);
            }
        }

        return Uang::jumlahkan($nilai);
    }

    /**
     * Komitmen level akun = Σ rab_item.jumlah akun tsb pada RAB aktif pengajuan yang mengikat pagu.
     */
    private function komitmenAkun(string $tahunAnggaranId, string $unitId, string $akunId, ?string $kecualiPengajuanId): string
    {
        $rabAktif = $this->pengajuanMengikat($tahunAnggaranId, $unitId, $kecualiPengajuanId)->whereNotNull('rab_aktif_id')->pluck('rab_aktif_id');

        return Uang::jumlahkan(
            RabItem::query()
                ->whereIn('rab_id', $rabAktif)
                ->where('akun_id', $akunId)
                ->pluck('jumlah')
                ->map(fn ($j): string => (string) $j)
                ->all(),
        );
    }

    /**
     * @return Builder<Pengajuan>
     */
    private function pengajuanMengikat(string $tahunAnggaranId, string $unitId, ?string $kecualiPengajuanId): Builder
    {
        $statusMengikat = array_map(
            fn (StatusPengajuan $s): string => $s->value,
            array_filter(StatusPengajuan::cases(), fn (StatusPengajuan $s): bool => $s->menghitungKomitmen() !== null),
        );

        return Pengajuan::query()
            ->where('tahun_anggaran_id', $tahunAnggaranId)
            ->where('unit_id', $unitId)
            ->whereIn('status', array_values($statusMengikat))
            ->when($kecualiPengajuanId, fn ($q) => $q->whereKeyNot($kecualiPengajuanId));
    }
}
