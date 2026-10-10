<?php

namespace App\Filament\Resources\TahunAnggarans\RelationManagers;

use App\Actions\Periode\BukaKunciPeriode;
use App\Actions\Periode\KunciPeriode;
use App\Exceptions\KesalahanDomain;
use App\Models\Periode;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Periode bulanan: kunci (admin-keuangan, dengan pra-cek), buka kunci beralasan (wadek), lihat snapshot.
 */
class PeriodeRelationManager extends RelationManager
{
    protected static string $relationship = 'periode';

    protected static ?string $title = 'Periode';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('pengunci'))
            ->paginated(false)
            ->columns([
                TextColumn::make('nama')->label('Periode'),
                TextColumn::make('tanggal_mulai')->label('Mulai')->date('d M Y')->toggleable(),
                TextColumn::make('tanggal_selesai')->label('Selesai')->date('d M Y')->toggleable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('pengunci.name')->label('Dikunci oleh')->placeholder('—'),
                TextColumn::make('dikunci_pada')->label('Dikunci pada')->dateTime('d M Y H:i')->placeholder('—'),
                TextColumn::make('versi_kunci')->label('Versi'),
            ])
            ->recordActions([
                Action::make('kunci')
                    ->label('Kunci')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->color('warning')
                    ->visible(fn (Periode $record): bool => auth()->user()?->can('kunci', $record) ?? false)
                    ->modalHeading(fn (Periode $record): string => "Kunci {$record->nama}?")
                    ->modalDescription(function (Periode $record): string {
                        $peringatan = KunciPeriode::peringatan($record);

                        return $peringatan === []
                            ? 'Pra-cek bersih. Angka periode akan disimpan sebagai snapshot.'
                            : 'Pra-cek: '.implode(' ', $peringatan);
                    })
                    ->schema([Checkbox::make('paham')->label('Saya memahami peringatan')])
                    ->action(fn (Periode $record, array $data) => $this->jalankan(
                        fn (User $u) => app(KunciPeriode::class)->handle($record, $u, (bool) ($data['paham'] ?? false)),
                        "{$record->nama} dikunci",
                    )),
                Action::make('bukaKunci')
                    ->label('Buka kunci')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->color('danger')
                    ->visible(fn (Periode $record): bool => auth()->user()?->can('bukaKunci', $record) ?? false)
                    ->schema([Textarea::make('alasan')->label('Alasan (wajib)')->required()->minLength(BukaKunciPeriode::MIN_ALASAN)])
                    ->action(fn (Periode $record, array $data) => $this->jalankan(
                        fn (User $u) => app(BukaKunciPeriode::class)->handle($record, (string) $data['alasan'], $u),
                        "{$record->nama} dibuka kembali",
                    )),
                Action::make('snapshot')
                    ->label('Lihat snapshot')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->color('gray')
                    ->visible(fn (Periode $record): bool => $record->versi_kunci > 0 && (auth()->user()?->can('lihatSnapshot', $record) ?? false))
                    ->modalHeading(fn (Periode $record): string => "Snapshot {$record->nama} (versi {$record->versi_kunci})")
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalContent(fn (Periode $record) => view('filament.snapshot-periode', ['rekap' => $record->rekap()->with('unit')->orderBy('unit_id')->get()])),
            ]);
    }

    /**
     * @param  callable(User): mixed  $aksi
     */
    private function jalankan(callable $aksi, string $sukses): void
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        try {
            $aksi($pengguna);
        } catch (KesalahanDomain|AuthorizationException $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($sukses)->success()->send();
    }
}
