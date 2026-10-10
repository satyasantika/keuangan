<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Pengajuans\PengajuanResource;
use App\Models\Pengajuan;
use App\Models\User;
use App\Queries\MenungguTindakanQuery;
use App\Support\Uang;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Antrean "Menunggu tindakan saya" untuk penyetuju (US-STJ-04).
 */
class MenungguTindakan extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'menunggu-tindakan';

    protected static ?string $title = 'Menunggu tindakan saya';

    protected static ?string $navigationLabel = 'Menunggu tindakan';

    protected static string|UnitEnum|null $navigationGroup = 'Beranda';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('persetujuan.setujui') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pengguna = auth()->user();

        if (! $pengguna instanceof User) {
            return null;
        }

        $jumlah = MenungguTindakanQuery::untuk($pengguna)->count();

        return $jumlah > 0 ? (string) $jumlah : null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        return $table
            ->query(MenungguTindakanQuery::untuk($pengguna))
            ->defaultSort('diajukan_pada', 'asc')
            ->emptyStateHeading('Tidak ada pengajuan yang menunggu tindakan Anda')
            ->recordUrl(fn (Pengajuan $record): string => PengajuanResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('nomor')->label('Nomor')->searchable(),
                TextColumn::make('unit.kode')->label('Unit'),
                TextColumn::make('judul_kegiatan')->label('Judul')->wrap()->limit(60),
                TextColumn::make('total_diajukan')->label('Total')->alignEnd()->formatStateUsing(fn ($state): string => Uang::format((string) $state)),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('diajukan_pada')->label('Menunggu sejak')->since()->sortable(),
            ]);
    }
}
