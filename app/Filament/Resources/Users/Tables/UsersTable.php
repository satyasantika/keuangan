<?php

namespace App\Filament\Resources\Users\Tables;

use App\Actions\Pengguna\AturWajibGantiSandi;
use App\Actions\Pengguna\MulaiImpersonasi;
use App\Enums\Peran;
use App\Models\User;
use App\Support\Impersonasi;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['roles', 'unit']))
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Surel')->searchable()->sortable(),
                TextColumn::make('nip')->label('NIP')->searchable()->toggleable(),
                TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Peran::tryFrom($state)?->getLabel() ?? $state),
                TextColumn::make('unit.kode')->label('Unit')->placeholder('—')->sortable(),
                IconColumn::make('wajib_ganti_sandi')->label('Wajib ganti sandi')->boolean()->toggleable(),
                ToggleColumn::make('aktif')
                    ->label('Aktif')
                    ->disabled(fn (User $record): bool => ! auth()->user()?->can('update', $record)),
            ])
            ->filters([
                SelectFilter::make('peran')
                    ->label('Peran')
                    ->options(collect(Peran::cases())->mapWithKeys(fn (Peran $p) => [$p->value => $p->getLabel()])->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('roles', fn (Builder $q) => $q->where('name', $data['value']))
                        : $query),
                TernaryFilter::make('aktif')->label('Aktif'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('resetMfa')
                    ->label('Reset MFA')
                    ->icon(Heroicon::OutlinedShieldExclamation)
                    ->color('danger')
                    ->authorize('resetMfa')
                    ->visible(fn (User $record): bool => $record->sudahAktifkanMfa())
                    ->requiresConfirmation()
                    ->modalDescription('Pengguna harus mendaftarkan ulang aplikasi autentikator pada login berikutnya.')
                    ->action(function (User $record): void {
                        $record->forceFill([
                            'app_authentication_secret' => null,
                            'app_authentication_recovery_codes' => null,
                        ])->save();

                        activity('users')
                            ->performedOn($record)
                            ->causedBy(auth()->user())
                            ->event('reset-mfa')
                            ->log('Reset MFA');

                        Notification::make()->title('MFA direset')->success()->send();
                    }),
                Action::make('impersonasi')
                    ->label('Masuk sebagai')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->color('warning')
                    ->authorize('impersonate')
                    ->visible(fn (User $record): bool => ! Impersonasi::aktif() && (bool) auth()->user()?->can('impersonate', $record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => "Masuk sebagai {$record->name}?")
                    ->modalDescription('Hanya untuk keadaan darurat. Seluruh tindakan Anda selama menyamar tercatat di jejak audit atas nama pengguna ini dengan penanda impersonasi. Profil dan kata sandi pengguna tidak dapat diubah selama menyamar.')
                    ->action(function (User $record) {
                        app(MulaiImpersonasi::class)->handle(auth()->user(), $record);

                        return redirect()->to(Filament::getUrl());
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('wajibkanGantiSandi')
                        ->label('Wajibkan ganti kata sandi')
                        ->icon(Heroicon::OutlinedKey)
                        ->visible(fn (): bool => (bool) auth()->user()?->hasRole(Peran::SuperAdmin->value))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $jumlah = app(AturWajibGantiSandi::class)->handle($records, true, auth()->user());
                            Notification::make()->title("{$jumlah} pengguna wajib mengganti kata sandi")->success()->send();
                        }),
                    BulkAction::make('cabutWajibGantiSandi')
                        ->label('Cabut kewajiban ganti kata sandi')
                        ->icon(Heroicon::OutlinedLockOpen)
                        ->visible(fn (): bool => (bool) auth()->user()?->hasRole(Peran::SuperAdmin->value))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            app(AturWajibGantiSandi::class)->handle($records, false, auth()->user());
                            Notification::make()->title('Kewajiban ganti kata sandi dicabut')->success()->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
