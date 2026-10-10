<?php

namespace App\Filament\Pages;

use App\Data\ParameterLaporan;
use App\Filament\Widgets\AntreanPersetujuanWidget;
use App\Filament\Widgets\RingkasanPaguWidget;
use App\Filament\Widgets\SerapanPerUnitChart;
use App\Filament\Widgets\SpjJatuhTempoWidget;
use App\Filament\Widgets\TrenRealisasiBulananChart;
use App\Models\TahunAnggaran;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Dasbor anggaran vs realisasi per peran (US-LAP-01); data dari LaporanRealisasiQuery (cache berversi).
 */
class Dasbor extends Dashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Dasbor';

    protected static string|\UnitEnum|null $navigationGroup = 'Beranda';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    Select::make('tahun_anggaran_id')
                        ->label('Tahun anggaran')
                        ->options(fn (): array => TahunAnggaran::query()->orderByDesc('tahun')->pluck('nama', 'id')->all())
                        ->default(fn (): ?string => TahunAnggaran::bawaanId()),
                    Select::make('jenis_periode')
                        ->label('Periode')
                        ->options([ParameterLaporan::TAHUNAN => 'Tahunan', ParameterLaporan::SEMESTER => 'Semester', ParameterLaporan::BULANAN => 'Bulanan'])
                        ->default(ParameterLaporan::TAHUNAN)
                        ->live(),
                    Select::make('nilai_periode')
                        ->label('Semester/bulan')
                        ->options(fn (Get $get): array => $get('jenis_periode') === ParameterLaporan::SEMESTER
                            ? [1 => 'Semester 1 (Jan–Jun)', 2 => 'Semester 2 (Jul–Des)']
                            : array_combine(range(1, 12), ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']))
                        ->visible(fn (Get $get): bool => in_array($get('jenis_periode'), [ParameterLaporan::SEMESTER, ParameterLaporan::BULANAN], true)),
                ]),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            RingkasanPaguWidget::class,
            AntreanPersetujuanWidget::class,
            SerapanPerUnitChart::class,
            TrenRealisasiBulananChart::class,
            SpjJatuhTempoWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
