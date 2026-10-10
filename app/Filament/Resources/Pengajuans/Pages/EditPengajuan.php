<?php

namespace App\Filament\Resources\Pengajuans\Pages;

use App\Actions\Pengajuan\SimpanDrafPengajuan;
use App\Exceptions\KesalahanDomain;
use App\Filament\Resources\Pengajuans\PengajuanResource;
use App\Models\Pengajuan;
use App\Models\RabItem;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * @property Pengajuan $record
 */
class EditPengajuan extends EditRecord
{
    protected static string $resource = PengajuanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->rabAktif?->item()->get()
            ->map(fn (RabItem $item): array => [
                'akun_id' => $item->akun_id,
                'uraian' => $item->uraian,
                'rincian_volume' => $item->rincian_volume,
                'volume' => $this->ringkas($item->volume),
                'satuan' => $item->satuan,
                'harga_satuan' => $this->ringkas($item->harga_satuan),
                'keterangan' => $item->keterangan,
            ])
            ->all() ?? [];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Pengajuan $record */
        try {
            return app(SimpanDrafPengajuan::class)->handle(Arr::except($data, ['items']), array_values($data['items'] ?? []), auth()->user(), $record);
        } catch (KesalahanDomain $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    /**
     * "2.00" → "2", "0.50" → "0.5" agar nyaman disunting.
     */
    private function ringkas(string $angka): string
    {
        return str_contains($angka, '.') ? rtrim(rtrim($angka, '0'), '.') : $angka;
    }
}
