<?php

namespace App\Filament\Resources\Pengajuans\Pages;

use App\Actions\Pengajuan\SimpanDrafPengajuan;
use App\Exceptions\KesalahanDomain;
use App\Filament\Resources\Pengajuans\PengajuanResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreatePengajuan extends CreateRecord
{
    protected static string $resource = PengajuanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SimpanDrafPengajuan::class)->handle(Arr::except($data, ['items']), array_values($data['items'] ?? []), auth()->user());
        } catch (KesalahanDomain $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
