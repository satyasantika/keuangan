<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\MemakaiParameterDasbor;
use App\Models\User;
use App\Queries\LaporanRealisasiQuery;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Pagu vs realisasi per unit (bar horizontal) — hanya peran fakultas.
 */
class SerapanPerUnitChart extends ChartWidget
{
    use MemakaiParameterDasbor;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Pagu vs realisasi per unit';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $pengguna = auth()->user();

        return $pengguna instanceof User && $pengguna->lingkupFakultas() && $pengguna->can('dasbor.lihat');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $parameter = $this->parameterLaporan();
        $baris = $parameter === null ? collect() : LaporanRealisasiQuery::untuk($parameter, $pengguna);

        return [
            'labels' => $baris->pluck('unit_kode')->all(),
            'datasets' => [
                ['label' => 'Pagu', 'data' => $baris->map(fn (array $b) => (float) $b['pagu'])->all(), 'backgroundColor' => 'rgba(148, 163, 184, 0.6)'],
                ['label' => 'Realisasi kumulatif', 'data' => $baris->map(fn (array $b) => (float) $b['realisasi_kumulatif'])->all(), 'backgroundColor' => 'rgba(245, 158, 11, 0.8)'],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                indexAxis: 'y',
                scales: {
                    x: { ticks: { callback: (v) => v >= 1e9 ? (v / 1e9).toLocaleString('id-ID') + ' M' : (v >= 1e6 ? (v / 1e6).toLocaleString('id-ID') + ' jt' : v.toLocaleString('id-ID')) } },
                },
                plugins: {
                    tooltip: { callbacks: { label: (c) => c.dataset.label + ': Rp' + c.parsed.x.toLocaleString('id-ID', { minimumFractionDigits: 2 }) } },
                },
            }
        JS);
    }
}
