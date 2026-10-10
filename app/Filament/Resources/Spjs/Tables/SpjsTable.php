<?php

namespace App\Filament\Resources\Spjs\Tables;

use App\Enums\StatusSpj;
use App\Models\Spj;
use App\Models\Unit;
use App\Models\User;
use App\Support\Uang;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SpjsTable
{
    public static function configure(Table $table): Table
    {
        $rupiah = fn ($state): string => Uang::format((string) $state);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('pengajuan.unit'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nomor')->label('Nomor SPJ')->placeholder('—')->searchable(),
                TextColumn::make('pengajuan.nomor')->label('Nomor pengajuan')->searchable(),
                TextColumn::make('pengajuan.unit.kode')->label('Unit'),
                TextColumn::make('total_realisasi')->label('Realisasi')->alignEnd()->formatStateUsing($rupiah),
                TextColumn::make('total_pajak')->label('Pajak')->alignEnd()->formatStateUsing($rupiah)->toggleable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('pengajuan.batas_spj')
                    ->label('Batas SPJ')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->color(fn (Spj $record): ?string => $record->status !== StatusSpj::Diterima && $record->pengajuan->batas_spj?->isPast() ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(StatusSpj::class),
                SelectFilter::make('unit')
                    ->label('Unit')
                    ->options(fn (): array => Unit::query()->orderBy('kode')->pluck('kode', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('pengajuan', fn (Builder $q) => $q->where('unit_id', $data['value']))
                        : $query)
                    ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->lingkupFakultas()),
                Filter::make('terlambat')
                    ->label('Terlambat')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', '!=', StatusSpj::Diterima->value)
                        ->whereHas('pengajuan', fn (Builder $q) => $q->whereDate('batas_spj', '<', now()->toDateString()))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
