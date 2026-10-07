<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Peran;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Surel')->searchable()->sortable(),
                TextColumn::make('nip')->label('NIP')->searchable()->toggleable(),
                TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Peran::tryFrom($state)?->getLabel() ?? $state),
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
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
