<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\RevisiRabs\Actions\AksiRevisi;
use App\Models\User;
use App\Queries\MenungguTindakanQuery;
use App\Support\Uang;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Revisi RAB yang menunggu tindakan pengguna (tab kedua halaman "Menunggu tindakan").
 */
class RevisiMenungguWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Revisi RAB menunggu tindakan';

    public static function canView(): bool
    {
        return auth()->user()?->can('revisi.setujui') ?? false;
    }

    public function table(Table $table): Table
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        return $table
            ->query(MenungguTindakanQuery::revisiUntuk($pengguna))
            ->defaultSort('diajukan_pada')
            ->emptyStateHeading('Tidak ada revisi yang menunggu tindakan Anda')
            ->columns([
                TextColumn::make('pengajuan.nomor')->label('Nomor pengajuan'),
                TextColumn::make('pengajuan.unit.kode')->label('Unit'),
                TextColumn::make('nomor_revisi')->label('Revisi ke-'),
                TextColumn::make('total')->label('Total')->alignEnd()->formatStateUsing(fn ($state): string => Uang::format((string) $state)),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('diajukan_pada')->label('Menunggu sejak')->since(),
            ])
            ->recordActions([
                AksiRevisi::bandingkan(),
                ...AksiRevisi::keputusan(),
            ]);
    }
}
