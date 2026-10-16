<?php

namespace App\Filament\Imports;

use App\Models\Akun;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

/**
 * Impor daftar akun BAS resmi. Kode yang sudah ada diperbarui nama/kelompoknya dan bersumber "impor".
 * Cache master:akun:aktif dihapus oleh event model setiap baris tersimpan.
 */
class AkunImporter extends Importer
{
    protected static ?string $model = Akun::class;

    protected static bool $shouldPreventFormulaInjection = true;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('kode')
                ->label('Kode')
                ->requiredMapping()
                ->rules(['required', 'regex:/^\d{6}$/'])
                ->examples(['521211']),
            ImportColumn::make('nama')
                ->label('Nama')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->examples(['Belanja Bahan']),
            ImportColumn::make('kelompok')
                ->label('Kelompok')
                ->rules(['nullable', 'max:10'])
                ->examples(['521']),
            ImportColumn::make('jenis_belanja')
                ->label('Jenis belanja')
                ->ignoreBlankState()
                ->rules(['nullable', 'in:'.implode(',', array_keys(Akun::JENIS_BELANJA))])
                ->examples(['barang']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getValidationMessages(): array
    {
        return ['kode.regex' => 'Kode akun harus 6 digit angka.'];
    }

    public function resolveRecord(): Akun
    {
        $this->data['kode'] = trim((string) $this->data['kode']);

        return Akun::withTrashed()->firstOrNew(['kode' => $this->data['kode']]);
    }

    protected function afterFill(): void
    {
        /** @var Akun $akun */
        $akun = $this->record;

        if (blank($this->data['kelompok'] ?? null)) {
            $akun->kelompok = substr($akun->kode, 0, 3);
        }

        $akun->sumber = 'impor';
    }

    protected function afterSave(): void
    {
        $akun = $this->record;

        if ($akun instanceof Akun && $akun->trashed()) {
            $akun->restore();
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
        $body = 'Impor akun selesai: '.Number::format($import->successful_rows).' baris berhasil.';

        if ($gagal = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($gagal).' baris gagal.';
        }

        return $body;
    }
}
