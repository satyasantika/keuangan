<?php

namespace App\Actions\Revisi;

use App\Actions\Audit\CatatRiwayatStatus;
use App\Actions\Pengajuan\HitungTotalRab;
use App\Enums\StatusRab;
use App\Exceptions\KesalahanDomain;
use App\Models\Pengajuan;
use App\Models\Rab;
use App\Models\RabItem;
use App\Models\User;
use App\Support\DenganKunci;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Revisi RAB bernomor 1, 2, … disalin dari RAB aktif (BR-10, BR-11).
 */
class BuatRevisiRab
{
    public function __construct(
        private readonly CatatRiwayatStatus $catatRiwayat,
        private readonly HitungTotalRab $hitungTotalRab,
    ) {}

    public function handle(Pengajuan $pengajuan, string $alasan, User $oleh): Rab
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 10) {
            throw new KesalahanDomain('Alasan revisi wajib diisi minimal 10 karakter.');
        }

        if (! $oleh->can('create', [Rab::class, $pengajuan])) {
            throw new AuthorizationException('Revisi tidak dapat dibuat: pastikan pengajuan disetujui/dicairkan, SPJ belum diajukan, dan tidak ada revisi berjalan.');
        }

        return DenganKunci::jalankan("aksi:pengajuan:{$pengajuan->id}", fn (): Rab => DB::transaction(function () use ($pengajuan, $alasan, $oleh): Rab {
            $terkini = Pengajuan::query()->lockForUpdate()->findOrFail($pengajuan->getKey());

            if (! $oleh->can('create', [Rab::class, $terkini])) {
                throw new KesalahanDomain('Revisi tidak dapat dibuat pada kondisi pengajuan saat ini.');
            }

            $nomor = (int) Rab::query()->where('pengajuan_id', $terkini->id)->max('nomor_revisi') + 1;

            $revisi = Rab::create([
                'pengajuan_id' => $terkini->id,
                'nomor_revisi' => $nomor,
                'alasan_revisi' => $alasan,
                'dibuat_oleh' => $oleh->getKey(),
            ]);

            /** @var RabItem $item */
            foreach (RabItem::query()->where('rab_id', $terkini->rab_aktif_id)->orderBy('urutan')->get() as $item) {
                RabItem::create([
                    'rab_id' => $revisi->id,
                    'akun_id' => $item->akun_id,
                    'asal_rab_item_id' => $item->id,
                    'urutan' => $item->urutan,
                    'uraian' => $item->uraian,
                    'rincian_volume' => $item->rincian_volume,
                    'volume' => $item->volume,
                    'satuan' => $item->satuan,
                    'harga_satuan' => $item->harga_satuan,
                    'keterangan' => $item->keterangan,
                ]);
            }

            $this->hitungTotalRab->handle($revisi);
            $this->catatRiwayat->handle($revisi, null, StatusRab::Draf, $oleh, "Revisi ke-{$nomor}: {$alasan}");

            return $revisi->refresh();
        }));
    }
}
