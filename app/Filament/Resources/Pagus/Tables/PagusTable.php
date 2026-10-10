<?php

namespace App\Filament\Resources\Pagus\Tables;

use App\Actions\Master\TetapkanPagu;
use App\Actions\Master\UbahPaguDitetapkan;
use App\Enums\StatusPagu;
use App\Exceptions\KesalahanDomain;
use App\Filament\Resources\Pagus\Schemas\PaguForm;
use App\Models\Pagu;
use App\Models\TahunAnggaran;
use App\Models\Unit;
use App\Support\Uang;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PagusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['tahunAnggaran', 'unit']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('tahunAnggaran.tahun')->label('Tahun')->sortable(),
                TextColumn::make('unit.kode')->label('Unit')->searchable()->sortable(),
                TextColumn::make('unit.nama')->label('Nama unit')->toggleable(),
                TextColumn::make('jumlah')->label('Jumlah')->alignEnd()->formatStateUsing(fn ($state): string => Uang::format((string) $state)),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([
                SelectFilter::make('tahun_anggaran_id')
                    ->label('Tahun')
                    ->options(fn (): array => TahunAnggaran::query()->orderByDesc('tahun')->pluck('tahun', 'id')->all()),
                SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->options(fn (): array => Unit::query()->orderBy('kode')->pluck('kode', 'id')->all()),
                SelectFilter::make('status')->label('Status')->options(StatusPagu::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::aksiTetapkan(),
                self::aksiUbahPagu(),
                DeleteAction::make(),
            ]);
    }

    public static function aksiTetapkan(): Action
    {
        return Action::make('tetapkan')
            ->label('Tetapkan')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->authorize('tetapkan')
            ->requiresConfirmation()
            ->modalDescription('Pagu yang ditetapkan menjadi batas resmi pengajuan unit.')
            ->action(function (Pagu $record): void {
                try {
                    app(TetapkanPagu::class)->handle($record, auth()->user());
                } catch (KesalahanDomain $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Pagu ditetapkan')->success()->send();
            });
    }

    public static function aksiUbahPagu(): Action
    {
        return Action::make('ubahPagu')
            ->label('Ubah pagu')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('warning')
            ->authorize('ubahDitetapkan')
            ->schema([
                TextInput::make('jumlah')
                    ->label('Jumlah baru (Rp)')
                    ->required()
                    ->regex(PaguForm::POLA_UANG)
                    ->default(fn (Pagu $record): string => (string) $record->jumlah),
                Textarea::make('alasan')->label('Alasan')->required()->minLength(10),
            ])
            ->action(function (Pagu $record, array $data): void {
                try {
                    app(UbahPaguDitetapkan::class)->handle($record, (string) $data['jumlah'], (string) $data['alasan'], auth()->user());
                } catch (KesalahanDomain $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Pagu diubah')->success()->send();
            });
    }
}
