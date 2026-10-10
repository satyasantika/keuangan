<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JenisTautan: string implements HasLabel
{
    case Tor = 'tor';
    case SuratTugas = 'surat_tugas';
    case BuktiPencairan = 'bukti_pencairan';
    case Kuitansi = 'kuitansi';
    case Nota = 'nota';
    case Faktur = 'faktur';
    case DaftarHadir = 'daftar_hadir';
    case FotoKegiatan = 'foto_kegiatan';
    case BuktiSetor = 'bukti_setor';
    case Lainnya = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::Tor => 'TOR/KAK',
            self::SuratTugas => 'Surat tugas',
            self::BuktiPencairan => 'Bukti pencairan',
            self::Kuitansi => 'Kuitansi',
            self::Nota => 'Nota',
            self::Faktur => 'Faktur',
            self::DaftarHadir => 'Daftar hadir',
            self::FotoKegiatan => 'Foto kegiatan',
            self::BuktiSetor => 'Bukti setor',
            self::Lainnya => 'Lainnya',
        };
    }

    /**
     * Bukti keuangan memuat NPWP/rekening: wajib dibagikan terbatas ke domain unsil.ac.id (BR-15).
     */
    public function buktiKeuangan(): bool
    {
        return in_array($this, [self::BuktiPencairan, self::Kuitansi, self::Nota, self::Faktur, self::BuktiSetor], true);
    }
}
