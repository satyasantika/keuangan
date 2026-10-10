<?php

namespace App\Filament\Pages;

use App\Data\ParameterLaporan;
use App\Laporan\DataLaporan;
use App\Laporan\KatalogLaporan;
use App\Models\TahunAnggaran;
use App\Models\Unit;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

/**
 * Pemilihan laporan, pratinjau 50 baris, dan ekspor (stream langsung atau antrean).
 *
 * @property-read Schema $form
 */
class Laporan extends Page
{
    protected static ?string $slug = 'laporan';

    protected static ?string $title = 'Laporan';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $pengguna = auth()->user();

        return $pengguna instanceof User && KatalogLaporan::untuk($pengguna) !== [];
    }

    public function mount(): void
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        $this->form->fill([
            'jenis' => array_key_first(KatalogLaporan::untuk($pengguna)),
            'tahun_anggaran_id' => TahunAnggaran::bawaanId(),
            'jenis_periode' => ParameterLaporan::TAHUNAN,
            'format' => 'xlsx',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        return $schema
            ->statePath('data')
            ->columns(['default' => 1, 'md' => 3])
            ->components([
                Select::make('jenis')->label('Jenis laporan')->options(fn (): array => KatalogLaporan::untuk($pengguna))->required()->live()->columnSpan(['md' => 2]),
                Select::make('format')->label('Format')->options(['xlsx' => 'Excel (xlsx)', 'pdf' => 'PDF'])->required(),
                Select::make('tahun_anggaran_id')->label('Tahun anggaran')->options(fn (): array => TahunAnggaran::query()->orderByDesc('tahun')->pluck('nama', 'id')->all())->required()->live(),
                Select::make('jenis_periode')->label('Periode')->options(['tahunan' => 'Tahunan', 'semester' => 'Semester', 'bulanan' => 'Bulanan'])->live(),
                Select::make('nilai_periode')->label('Semester/bulan')
                    ->options(fn (Get $get): array => $get('jenis_periode') === 'semester' ? [1 => 'Semester 1', 2 => 'Semester 2'] : array_combine(range(1, 12), range(1, 12)))
                    ->live(),
                Select::make('unit_id')->label('Unit')
                    ->options(fn (): array => Unit::query()->orderBy('kode')->pluck('kode', 'id')->all())
                    ->visible(fn (): bool => $pengguna->lingkupFakultas())
                    ->live(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form'),
            View::make('filament.pages.pratinjau-laporan')->viewData(['pratinjau' => $this->pratinjau()]),
        ]);
    }

    /**
     * @return array{data: DataLaporan|null, galat: string|null}
     */
    public function pratinjau(): array
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $isian = $this->data ?? [];

        if (blank($isian['jenis'] ?? null) || blank($isian['tahun_anggaran_id'] ?? null)) {
            return ['data' => null, 'galat' => null];
        }

        try {
            return ['data' => KatalogLaporan::bangun((string) $isian['jenis'], $isian, $pengguna), 'galat' => null];
        } catch (Throwable $e) {
            return ['data' => null, 'galat' => $e->getMessage()];
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ekspor')
                ->label('Ekspor')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(fn (): string => route('laporan.ekspor', array_filter($this->data ?? [], fn ($v) => filled($v))))
                ->openUrlInNewTab(),
        ];
    }
}
