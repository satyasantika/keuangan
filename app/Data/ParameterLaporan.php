<?php

namespace App\Data;

use InvalidArgumentException;

/**
 * Parameter laporan realisasi: periode bulanan (1–12), semester (1–2; bulan 1–6 / 7–12, ADR 0002 butir 11),
 * atau tahunan.
 */
final readonly class ParameterLaporan
{
    public const BULANAN = 'bulanan';

    public const SEMESTER = 'semester';

    public const TAHUNAN = 'tahunan';

    public function __construct(
        public string $tahunAnggaranId,
        public string $jenisPeriode = self::TAHUNAN,
        public ?int $nilaiPeriode = null,
        public ?string $unitId = null,
        public ?string $akunId = null,
    ) {
        $sah = match ($jenisPeriode) {
            self::BULANAN => $nilaiPeriode !== null && $nilaiPeriode >= 1 && $nilaiPeriode <= 12,
            self::SEMESTER => in_array($nilaiPeriode, [1, 2], true),
            self::TAHUNAN => true,
            default => false,
        };

        if (! $sah) {
            throw new InvalidArgumentException('Parameter periode laporan tidak sah.');
        }
    }

    /**
     * @return array{0: int, 1: int} bulan awal & akhir rentang
     */
    public function rentangBulan(): array
    {
        return match ($this->jenisPeriode) {
            self::BULANAN => [(int) $this->nilaiPeriode, (int) $this->nilaiPeriode],
            self::SEMESTER => $this->nilaiPeriode === 1 ? [1, 6] : [7, 12],
            default => [1, 12],
        };
    }

    public function label(): string
    {
        $nama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return match ($this->jenisPeriode) {
            self::BULANAN => 'Bulan '.$nama[(int) $this->nilaiPeriode],
            self::SEMESTER => 'Semester '.$this->nilaiPeriode,
            default => 'Tahunan',
        };
    }

    public function kunciCache(): string
    {
        return implode(':', [$this->tahunAnggaranId, $this->jenisPeriode, $this->nilaiPeriode ?? '-', $this->unitId ?? 'semua', $this->akunId ?? 'semua']);
    }
}
