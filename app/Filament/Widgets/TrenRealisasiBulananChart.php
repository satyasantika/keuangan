<?php

namespace App\Filament\Widgets;

use App\Data\ParameterLaporan;
use App\Filament\Widgets\Concerns\MemakaiParameterDasbor;
use App\Models\User;
use App\Queries\LaporanRealisasiQuery;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Realisasi kumulatif per bulan tahun anggaran terpilih.
 */
class TrenRealisasiBulananChart extends ChartWidget
{
    use MemakaiParameterDasbor;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Tren realisasi kumulatif';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('dasbor.lihat') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $parameter = $this->parameterLaporan();
        $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $data = [];

        if ($parameter !== null) {
            foreach (range(1, 12) as $b) {
                $total = LaporanRealisasiQuery::total(LaporanRealisasiQuery::untuk(new ParameterLaporan($parameter->tahunAnggaranId, ParameterLaporan::BULANAN, $b), $pengguna));
                $data[] = (float) $total['realisasi_kumulatif'];
            }
        }

        return [
            'labels' => $bulan,
            'datasets' => [['label' => 'Realisasi kumulatif', 'data' => $data, 'borderColor' => 'rgb(245, 158, 11)', 'backgroundColor' => 'rgba(245, 158, 11, 0.15)', 'fill' => true]],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                scales: {
                    y: { ticks: { callback: (v) => v >= 1e9 ? (v / 1e9).toLocaleString('id-ID') + ' M' : (v >= 1e6 ? (v / 1e6).toLocaleString('id-ID') + ' jt' : v.toLocaleString('id-ID')) } },
                },
                plugins: {
                    tooltip: { callbacks: { label: (c) => 'Rp' + c.parsed.y.toLocaleString('id-ID', { minimumFractionDigits: 2 }) } },
                },
            }
        JS);
    }
}
