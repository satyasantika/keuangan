<?php

namespace App\Filament\Resources\Pencairans\Tables;

use App\Enums\StatusPencairan;
use App\Filament\Resources\Pencairans\Actions\AksiPencairan;
use App\Models\JenisPencairan;
use App\Models\Periode;
use App\Models\Unit;
use App\Models\User;
use App\Support\Uang;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PencairansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['pengajuan.unit', 'jenisPencairan']))
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('pengajuan.nomor')->label('Nomor pengajuan')->searchable(),
                TextColumn::make('pengajuan.unit.kode')->label('Unit'),
                TextColumn::make('jenisPencairan.kode')->label('Jenis'),
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('jumlah')->label('Jumlah')->alignEnd()->formatStateUsing(fn ($state): string => Uang::format((string) $state)),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([
                SelectFilter::make('jenis_pencairan_id')->label('Jenis')->options(fn (): array => JenisPencairan::query()->orderBy('kode')->pluck('kode', 'id')->all()),
                SelectFilter::make('periode_id')->label('Periode')->options(fn (): array => Periode::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id')->all()),
                SelectFilter::make('status')->label('Status')->options(StatusPencairan::class),
                SelectFilter::make('unit')
                    ->label('Unit')
                    ->options(fn (): array => Unit::query()->orderBy('kode')->pluck('kode', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('pengajuan', fn (Builder $q) => $q->where('unit_id', $data['value']))
                        : $query)
                    ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->lingkupFakultas()),
            ])
            ->recordActions([
                ViewAction::make(),
                AksiPencairan::batalkan(),
            ]);
    }
}
