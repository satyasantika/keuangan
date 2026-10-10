<?php

namespace App\Filament\Infolists;

use App\Contracts\PenyimpananBerkas;
use App\Enums\JenisTautan;
use App\Models\TautanBerkas;
use App\Models\User;
use Filament\Infolists\Components\ViewEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * Daftar tautan berkas pemilik dengan tombol "Buka berkas" dan pratinjau Drive.
 * Tautan hanya dirender untuk pengguna yang lolos Policy (BR-15).
 */
class DaftarTautanBerkas
{
    public const PLACEHOLDER_PRATINJAU = 'Pratinjau tidak dapat dimuat — buka berkas dengan akun unsil.ac.id';

    public static function make(string $relasi = 'tautanBerkas'): ViewEntry
    {
        return ViewEntry::make('daftar_tautan_berkas')
            ->hiddenLabel()
            ->view('filament.infolists.daftar-tautan-berkas')
            ->visible(fn (?Model $record): bool => $record !== null && (auth()->user()?->can('view', $record) ?? false))
            ->state(fn (?Model $record): array => $record === null ? [] : self::butir($record, $relasi));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function butir(Model $pemilik, string $relasi = 'tautanBerkas'): array
    {
        /** @var User|null $pengguna */
        $pengguna = auth()->user();
        $penyimpanan = app(PenyimpananBerkas::class);

        /** @var iterable<TautanBerkas> $daftar */
        $daftar = $pemilik->{$relasi}()->get();
        $hasil = [];

        foreach ($daftar as $tautan) {
            if (! $pengguna?->can('view', $tautan)) {
                continue;
            }

            $hasil[] = [
                'id' => $tautan->id,
                'label' => $tautan->label,
                'jenis' => $tautan->jenis->getLabel(),
                'status' => $tautan->status_cek->getLabel(),
                'warna_status' => $tautan->status_cek->getColor(),
                'peringatan_privasi' => $tautan->peringatan_privasi,
                'url' => $penyimpanan->urlTampil($tautan),
                'pratinjau' => $penyimpanan->urlPratinjau($tautan),
                'foto' => $tautan->jenis === JenisTautan::FotoKegiatan,
            ];
        }

        return $hasil;
    }
}
