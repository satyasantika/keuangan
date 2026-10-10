<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\MemakaiParameterDasbor;
use App\Models\User;
use App\Queries\LaporanRealisasiQuery;
use App\Support\Uang;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan pagu, disetujui, dicairkan, realisasi, serapan — lingkup sesuai pengguna.
 */
class RingkasanPaguWidget extends StatsOverviewWidget
{
    use MemakaiParameterDasbor;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $pengguna = auth()->user();

        return $pengguna instanceof User && $pengguna->can('dasbor.lihat') && ($pengguna->lingkupFakultas() || $pengguna->unit_id !== null);
    }

    protected function getHeading(): ?string
    {
        return 'Ringkasan anggaran';
    }

    protected function getStats(): array
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $parameter = $this->parameterLaporan();

        if ($parameter === null) {
            return [Stat::make('Pagu', '—')->description('Belum ada tahun anggaran')];
        }

        $total = LaporanRealisasiQuery::total(LaporanRealisasiQuery::untuk($parameter, $pengguna));
        $honor = LaporanRealisasiQuery::honorUjian($parameter, $pengguna);

        $stat = [
            Stat::make('Pagu', Uang::format($total['pagu']))->description('Sisa '.Uang::format($total['sisa_pagu'])),
            Stat::make('Disetujui', Uang::format($total['disetujui'])),
            Stat::make('Dicairkan', Uang::format($total['dicairkan']))->description($parameter->label()),
            Stat::make('Realisasi', Uang::format($total['realisasi']))->description($parameter->label()),
            Stat::make('Serapan', $total['serapan_persen'] === null ? '—' : str_replace('.', ',', $total['serapan_persen']).'%')
                ->description('Realisasi kumulatif / pagu')
                ->color('success'),
        ];

        if (Uang::bandingkan($honor, '0') > 0) {
            $stat[] = Stat::make('Honor ujian (impor fkipapp)', Uang::format($honor))
                ->description('Rekap baca-saja, terpisah dari realisasi kegiatan')
                ->color('gray');
        }

        return $stat;
    }
}
