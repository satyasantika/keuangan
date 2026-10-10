<?php

use App\Actions\Audit\CatatRiwayatStatus;
use App\Actions\Persetujuan\KembalikanPengajuan;
use App\Actions\Persetujuan\KeputusanNegatif;
use App\Actions\Persetujuan\SetujuiPengajuan;
use App\Actions\Persetujuan\TolakPengajuan;
use App\Actions\Persetujuan\VerifikasiPengajuan;
use App\Support\DenganKunci;

arch('aksi persetujuan memakai DenganKunci (BR-09)')
    ->expect([VerifikasiPengajuan::class, SetujuiPengajuan::class, KeputusanNegatif::class])
    ->toUse(DenganKunci::class);

arch('kembalikan dan tolak memakai dasar KeputusanNegatif yang berkunci')
    ->expect([KembalikanPengajuan::class, TolakPengajuan::class])
    ->toExtend(KeputusanNegatif::class);

arch('aksi persetujuan mencatat riwayat status (BR-08)')
    ->expect([VerifikasiPengajuan::class, SetujuiPengajuan::class, KeputusanNegatif::class])
    ->toUse(CatatRiwayatStatus::class);
