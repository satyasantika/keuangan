<?php

namespace App\Filament\Imports;

use App\Enums\JenisUnit;
use App\Models\Unit;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

/**
 * Impor daftar resmi unit (kode, nama, jenis, jenjang). Kode yang sudah ada diperbarui.
 */
class UnitImporter extends Importer
{
    protected static ?string $model = Unit::class;

    protected static bool $shouldPreventFormulaInjection = true;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('kode')
                ->label('Kode')
                ->requiredMapping()
                ->rules(['required', 'max:20'])
                ->examples(['PMAT']),
            ImportColumn::make('nama')
                ->label('Nama')
                ->requiredMapping()
                ->rules(['required', 'max:150'])
                ->examples(['Pendidikan Matematika']),
            ImportColumn::make('jenis')
                ->label('Jenis')
                ->enum(JenisUnit::class)
                ->ignoreBlankState(),
            ImportColumn::make('jenjang')
                ->label('Jenjang')
                ->ignoreBlankState()
                ->rules(['nullable', 'max:10'])
                ->examples(['S1']),
        ];
    }

    public function resolveRecord(): Unit
    {
        $kode = strtoupper(trim((string) $this->data['kode']));
        $this->data['kode'] = $kode;

        return Unit::withTrashed()->firstOrNew(['kode' => $kode]);
    }

    protected function afterSave(): void
    {
        $unit = $this->record;

        if ($unit instanceof Unit && $unit->trashed()) {
            $unit->restore();
        }
    }

    public function getJobConnection(): ?string
    {
        return 'database-berat';
    }

    public function getJobQueue(): ?string
    {
        return 'berat';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Impor unit selesai: '.Number::format($import->successful_rows).' baris berhasil.';

        if ($gagal = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($gagal).' baris gagal.';
        }

        return $body;
    }
}
