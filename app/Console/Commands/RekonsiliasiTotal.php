<?php

namespace App\Console\Commands;

use App\Actions\Pengajuan\HitungUlangTotalPengajuan;
use App\Enums\Peran;
use App\Models\Pengajuan;
use App\Models\User;
use App\Notifications\SelisihRekonsiliasi;
use App\Support\Uang;
use App\Support\VersiCacheDasbor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Membandingkan kolom total_* pengajuan dengan perhitungan ulang dari tabel sumber (BR-26).
 */
#[Signature('keuangan:rekonsiliasi {--perbaiki : Simpan hasil hitung ulang untuk baris yang berselisih}')]
#[Description('Periksa selisih kolom total denormalisasi pengajuan')]
class RekonsiliasiTotal extends Command
{
    public function handle(HitungUlangTotalPengajuan $hitungUlang): int
    {
        $selisih = [];
        $perbaiki = (bool) $this->option('perbaiki');

        Pengajuan::query()->withTrashed()->orderBy('id')->chunkById(200, function ($daftar) use ($hitungUlang, &$selisih, $perbaiki): void {
            /** @var Pengajuan $pengajuan */
            foreach ($daftar as $pengajuan) {
                $hitung = $hitungUlang->hitung($pengajuan);
                $beda = false;

                foreach ($hitung as $kolom => $seharusnya) {
                    $tersimpan = (string) $pengajuan->getAttribute($kolom);

                    if (Uang::bandingkan($tersimpan, $seharusnya) !== 0) {
                        $selisih[] = [$pengajuan->nomor ?? $pengajuan->id, $kolom, $tersimpan, $seharusnya, Uang::kurang($seharusnya, $tersimpan)];
                        $beda = true;
                    }
                }

                $spj = $pengajuan->spj()->first();

                if ($spj !== null) {
                    $hitungSpj = $hitungUlang->hitungSpj($spj);

                    foreach ($hitungSpj as $kolom => $seharusnya) {
                        $tersimpan = (string) $spj->getAttribute($kolom);

                        if (Uang::bandingkan($tersimpan, $seharusnya) !== 0) {
                            $selisih[] = [($pengajuan->nomor ?? $pengajuan->id).' (SPJ)', $kolom, $tersimpan, $seharusnya, Uang::kurang($seharusnya, $tersimpan)];

                            if ($perbaiki) {
                                $spj->forceFill($hitungSpj)->saveQuietly();
                            }
                        }
                    }
                }

                if ($beda && $perbaiki) {
                    $pengajuan->forceFill($hitung)->saveQuietly();
                }
            }
        });

        if ($selisih === []) {
            $this->info('Tidak ada selisih total.');

            return self::SUCCESS;
        }

        $this->table(['Pengajuan', 'Kolom', 'Tersimpan', 'Hitung ulang', 'Selisih'], $selisih);

        if ($perbaiki) {
            VersiCacheDasbor::naikkan();
            $this->warn(count($selisih).' selisih diperbaiki.');

            return self::SUCCESS;
        }

        $this->error(count($selisih).' selisih ditemukan. Jalankan dengan --perbaiki untuk menyimpan hasil hitung ulang.');

        $superAdmin = User::query()->where('aktif', true)->role(Peran::SuperAdmin->value)->get();

        if ($superAdmin->isNotEmpty()) {
            Notification::send($superAdmin, new SelisihRekonsiliasi(count($selisih)));
        }

        return self::FAILURE;
    }
}
