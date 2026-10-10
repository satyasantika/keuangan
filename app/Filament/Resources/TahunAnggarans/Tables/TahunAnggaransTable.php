<?php

namespace App\Filament\Resources\TahunAnggarans\Tables;

use App\Actions\Master\UbahStatusTahunAnggaran;
use App\Enums\StatusTahunAnggaran;
use App\Exceptions\KesalahanDomain;
use App\Models\TahunAnggaran;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TahunAnggaransTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('tahun', 'desc')
            ->columns([
                TextColumn::make('tahun')->label('Tahun')->sortable()->searchable(),
                TextColumn::make('nama')->label('Nama'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('tanggal_mulai')->label('Mulai')->date('d M Y'),
                TextColumn::make('tanggal_selesai')->label('Selesai')->date('d M Y'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusTahunAnggaran::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::aksiUbahStatus('mulaiBerjalan', 'Jalankan', StatusTahunAnggaran::Berjalan, Heroicon::OutlinedPlay, 'success'),
                self::aksiUbahStatus('tutupTahun', 'Tutup tahun', StatusTahunAnggaran::Ditutup, Heroicon::OutlinedLockClosed, 'danger'),
                DeleteAction::make(),
            ]);
    }

    public static function aksiUbahStatus(string $nama, string $label, StatusTahunAnggaran $ke, Heroicon $ikon, string $warna): Action
    {
        return Action::make($nama)
            ->label($label)
            ->icon($ikon)
            ->color($warna)
            ->requiresConfirmation()
            ->authorize('update')
            ->visible(fn (TahunAnggaran $record): bool => $record->status->bolehBerpindahKe($ke))
            ->action(function (TahunAnggaran $record) use ($ke): void {
                try {
                    app(UbahStatusTahunAnggaran::class)->handle($record, $ke, auth()->user());
                } catch (KesalahanDomain $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Status tahun anggaran diubah menjadi '.$ke->getLabel())->success()->send();
            });
    }
}
