<?php

namespace App\Filament\Widgets;

use App\Enums\Peran;
use App\Enums\TahapPersetujuan;
use App\Models\User;
use App\Queries\MenungguTindakanQuery;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Jumlah pengajuan menunggu per tahap persetujuan, untuk peran penyetuju.
 */
class AntreanPersetujuanWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    /**
     * Dasbor: admin-keuangan & bpp (F11.2); penyetuju lain memakai halaman "Menunggu tindakan".
     */
    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole([Peran::AdminKeuangan->value, Peran::Bpp->value]) ?? false;
    }

    protected function getHeading(): ?string
    {
        return 'Antrean persetujuan';
    }

    protected function getStats(): array
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $unitId = $pengguna->lingkupFakultas() || ! $pengguna->hasRole(Peran::KepalaUnit->value) ? null : $pengguna->unit_id;
        $jumlah = MenungguTindakanQuery::jumlahPerTahap($unitId);

        return [
            Stat::make('Menunggu tindakan saya', (string) MenungguTindakanQuery::untuk($pengguna)->count())->color('warning'),
            Stat::make(TahapPersetujuan::KepalaUnit->getLabel(), (string) $jumlah[TahapPersetujuan::KepalaUnit->value]),
            Stat::make(TahapPersetujuan::Keuangan->getLabel(), (string) $jumlah[TahapPersetujuan::Keuangan->value]),
            Stat::make(TahapPersetujuan::Wadek->getLabel(), (string) $jumlah[TahapPersetujuan::Wadek->value]),
        ];
    }
}
