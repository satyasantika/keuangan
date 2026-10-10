<?php

namespace App\Support;

use App\Filament\Resources\Pencairans\PencairanResource;
use App\Filament\Resources\Pengajuans\PengajuanResource;
use App\Filament\Resources\Pengembalians\PengembalianResource;
use App\Filament\Resources\RevisiRabs\RevisiRabResource;
use App\Filament\Resources\Spjs\SpjResource;
use App\Filament\Resources\TahunAnggarans\TahunAnggaranResource;

/**
 * URL halaman aplikasi untuk notifikasi (berlaku juga di luar request panel, mis. di antrean).
 */
final class TautanAplikasi
{
    private const PANEL = 'keuangan';

    public static function pengajuan(string $id): string
    {
        return PengajuanResource::getUrl('view', ['record' => $id], panel: self::PANEL);
    }

    public static function pencairan(string $id): string
    {
        return PencairanResource::getUrl('view', ['record' => $id], panel: self::PANEL);
    }

    public static function spj(string $id): string
    {
        return SpjResource::getUrl('view', ['record' => $id], panel: self::PANEL);
    }

    public static function pengembalian(string $id): string
    {
        return PengembalianResource::getUrl('view', ['record' => $id], panel: self::PANEL);
    }

    public static function revisi(): string
    {
        return RevisiRabResource::getUrl('index', panel: self::PANEL);
    }

    public static function tahunAnggaran(string $id): string
    {
        return TahunAnggaranResource::getUrl('edit', ['record' => $id], panel: self::PANEL);
    }
}
