<?php

namespace App\Filament\Resources\Pengembalians;

use App\Enums\StatusPengembalian;
use App\Filament\Infolists\DaftarTautanBerkas;
use App\Filament\Resources\Pengembalians\Actions\AksiPengembalian;
use App\Filament\Resources\Pengembalians\Pages\ListPengembalians;
use App\Filament\Resources\Pengembalians\Pages\ViewPengembalian;
use App\Models\Pengembalian;
use App\Models\User;
use App\Support\Uang;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PengembalianResource extends Resource
{
    protected static ?string $model = Pengembalian::class;

    protected static ?string $slug = 'pengembalian';

    protected static ?string $modelLabel = 'Pengembalian';

    protected static ?string $pluralModelLabel = 'Pengembalian Sisa Dana';

    protected static ?string $navigationLabel = 'Pengembalian';

    protected static string|UnitEnum|null $navigationGroup = 'Bendahara';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnDown;

    public static function infolist(Schema $schema): Schema
    {
        $rupiah = fn ($state): string => Uang::format((string) $state);

        return $schema->columns(1)->components([
            Section::make('Pengembalian')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('pengajuan.nomor')->label('Nomor pengajuan'),
                    TextEntry::make('pengajuan.unit.nama')->label('Unit'),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('tanggal_setor')->label('Tanggal setor')->date('d F Y'),
                    TextEntry::make('jumlah')->label('Jumlah')->formatStateUsing($rupiah),
                    TextEntry::make('nomor_bukti_setor')->label('Nomor bukti setor'),
                    TextEntry::make('catatan')->label('Catatan')->placeholder('—'),
                ]),
            Section::make('Bukti setor (tautan)')->schema([DaftarTautanBerkas::make()]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('pengajuan.unit'))
            ->defaultSort('tanggal_setor', 'desc')
            ->columns([
                TextColumn::make('pengajuan.nomor')->label('Nomor pengajuan')->searchable(),
                TextColumn::make('pengajuan.unit.kode')->label('Unit'),
                TextColumn::make('tanggal_setor')->label('Tanggal setor')->date('d M Y'),
                TextColumn::make('jumlah')->label('Jumlah')->alignEnd()->formatStateUsing(fn ($state): string => Uang::format((string) $state)),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([SelectFilter::make('status')->label('Status')->options(StatusPengembalian::class)])
            ->recordActions([
                ViewAction::make(),
                AksiPengembalian::verifikasi(),
                AksiPengembalian::tolak(),
            ]);
    }

    /**
     * @return Builder<Pengembalian>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        /** @var Builder<Pengembalian> $query */
        $query = parent::getEloquentQuery();

        return $pengguna->lingkupFakultas() ? $query : $query->whereHas('pengajuan', fn (Builder $q) => $q->terlihatOleh($pengguna));
    }

    /**
     * @return Builder<Pengembalian>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        /** @var Builder<Pengembalian> $query */
        $query = parent::getEloquentQuery();

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPengembalians::route('/'),
            'view' => ViewPengembalian::route('/{record}'),
        ];
    }
}
