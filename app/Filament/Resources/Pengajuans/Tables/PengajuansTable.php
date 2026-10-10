<?php

namespace App\Filament\Resources\Pengajuans\Tables;

use App\Enums\StatusPengajuan;
use App\Models\TahunAnggaran;
use App\Models\Unit;
use App\Models\User;
use App\Support\Uang;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PengajuansTable
{
    public static function configure(Table $table): Table
    {
        $rupiah = fn ($state): string => Uang::format((string) $state);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['unit', 'tahunAnggaran']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nomor')
                    ->label('Nomor')
                    ->placeholder('—')
                    ->sortable()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        fn (Builder $q) => $q->where('nomor', 'like', "%{$search}%")
                            ->orWhereFullText('judul_kegiatan', $search, ['mode' => 'boolean']),
                    )),
                TextColumn::make('judul_kegiatan')->label('Judul')->wrap()->limit(60),
                TextColumn::make('unit.kode')->label('Unit'),
                TextColumn::make('total_diajukan')->label('Diajukan')->alignEnd()->formatStateUsing($rupiah),
                TextColumn::make('total_disetujui')->label('Disetujui')->alignEnd()->formatStateUsing($rupiah)->toggleable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('batas_spj')->label('Batas SPJ')->date('d M Y')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tahun_anggaran_id')
                    ->label('Tahun')
                    ->options(fn (): array => TahunAnggaran::query()->orderByDesc('tahun')->pluck('tahun', 'id')->all()),
                SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->options(fn (): array => Unit::query()->orderBy('kode')->pluck('kode', 'id')->all())
                    ->visible(function (): bool {
                        $pengguna = auth()->user();

                        return $pengguna instanceof User && $pengguna->lingkupFakultas();
                    }),
                SelectFilter::make('status')->label('Status')->options(StatusPengajuan::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
