<?php

namespace App\Filament\Widgets;

use App\Actions\Pengajuan\HitungSisaPagu;
use App\Enums\StatusTahunAnggaran;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Support\Uang;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Pagu, komitmen, dan sisa pagu unit pengguna (US-PAGU-03) untuk admin-unit/kepala-unit.
 */
class RingkasanPaguWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $pengguna = auth()->user();

        return $pengguna instanceof User && $pengguna->unit_id !== null && ! $pengguna->lingkupFakultas() && $pengguna->can('pagu.lihat');
    }

    protected function getHeading(): ?string
    {
        return 'Pagu unit';
    }

    protected function getStats(): array
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $tahun = TahunAnggaran::query()->where('status', StatusTahunAnggaran::Berjalan->value)->orderByDesc('tahun')->first();

        if ($tahun === null || $pengguna->unit_id === null) {
            return [Stat::make('Pagu', '—')->description('Belum ada tahun anggaran berjalan')];
        }

        $ringkas = app(HitungSisaPagu::class)->handle($tahun->id, $pengguna->unit_id);

        return [
            Stat::make("Pagu {$tahun->tahun}", Uang::format($ringkas['pagu'] ?? '0')),
            Stat::make('Komitmen', Uang::format($ringkas['komitmen'] ?? '0'))->description('Pengajuan yang sedang/telah disetujui'),
            Stat::make('Sisa pagu', Uang::format($ringkas['sisa'] ?? '0'))
                ->color(Uang::bandingkan($ringkas['sisa'] ?? '0', '0') > 0 ? 'success' : 'danger'),
        ];
    }
}
