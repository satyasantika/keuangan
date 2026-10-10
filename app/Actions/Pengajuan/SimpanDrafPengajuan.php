<?php

namespace App\Actions\Pengajuan;

use App\Enums\StatusRab;
use App\Enums\StatusTahunAnggaran;
use App\Exceptions\KesalahanDomain;
use App\Models\Pengajuan;
use App\Models\Rab;
use App\Models\RabItem;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Support\DenganKunci;
use App\Support\Uang;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Membuat/memperbarui pengajuan draf beserta item RAB versi 0 (BR-02, BR-03).
 * Nilai jumlah/total kiriman klien diabaikan; dihitung ulang oleh HitungTotalRab.
 */
class SimpanDrafPengajuan
{
    private const POLA_DESIMAL = '/^\d+(\.\d{1,2})?$/';

    public function __construct(private readonly HitungTotalRab $hitungTotalRab) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(array $data, array $items, User $oleh, ?Pengajuan $pengajuan = null): Pengajuan
    {
        if ($pengajuan === null ? ! $oleh->can('create', Pengajuan::class) : ! $oleh->can('update', $pengajuan)) {
            throw new AuthorizationException('Anda tidak berhak menyimpan pengajuan ini.');
        }

        [$data, $items] = $this->validasi($data, $items);

        $simpan = fn (): Pengajuan => DB::transaction(function () use ($data, $items, $oleh, $pengajuan): Pengajuan {
            if ($pengajuan !== null) {
                $pengajuan = Pengajuan::query()->lockForUpdate()->findOrFail($pengajuan->getKey());

                if (! $pengajuan->status->dapatDiubah()) {
                    throw new KesalahanDomain('Pengajuan hanya dapat diubah saat berstatus draf atau dikembalikan.');
                }
            }

            $pengajuan ??= new Pengajuan(['dibuat_oleh' => $oleh->getKey()]);
            // Unit selalu unit pengguna; unit kiriman klien diabaikan (BR-21).
            $pengajuan->fill([...$data, 'unit_id' => $oleh->unit_id])->save();

            $rab = $pengajuan->rabAwal()->first() ?? Rab::create([
                'pengajuan_id' => $pengajuan->id,
                'nomor_revisi' => 0,
                'dibuat_oleh' => $oleh->getKey(),
            ]);

            if ($rab->status !== StatusRab::Draf) {
                throw new KesalahanDomain('RAB sudah terkunci.');
            }

            if ($pengajuan->rab_aktif_id !== $rab->id) {
                $pengajuan->forceFill(['rab_aktif_id' => $rab->id])->save();
            }

            $rab->item()->get()->each(fn (RabItem $item) => $item->delete());

            foreach (array_values($items) as $urutan => $item) {
                RabItem::create([
                    'rab_id' => $rab->id,
                    'akun_id' => $item['akun_id'],
                    'urutan' => $urutan + 1,
                    'uraian' => $item['uraian'],
                    'rincian_volume' => $item['rincian_volume'] ?? null,
                    'volume' => $item['volume'],
                    'satuan' => $item['satuan'],
                    'harga_satuan' => $item['harga_satuan'],
                    'keterangan' => $item['keterangan'] ?? null,
                ]);
            }

            $this->hitungTotalRab->handle($rab);

            return $pengajuan->refresh();
        });

        return $pengajuan === null ? $simpan() : DenganKunci::jalankan("aksi:pengajuan:{$pengajuan->id}", $simpan);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function validasi(array $data, array $items): array
    {
        $tervalidasi = Validator::make(
            ['data' => $data, 'items' => array_values($items)],
            [
                'data.tahun_anggaran_id' => ['required', 'string', Rule::exists('tahun_anggaran', 'id')->whereNull('deleted_at')],
                'data.kategori_penggunaan_dana_id' => ['nullable', 'string', Rule::exists('kategori_penggunaan_dana', 'id')->whereNull('deleted_at')],
                'data.judul_kegiatan' => ['required', 'string', 'max:255'],
                'data.deskripsi' => ['nullable', 'string'],
                'data.tanggal_mulai_kegiatan' => ['required', 'date'],
                'data.tanggal_selesai_kegiatan' => ['required', 'date', 'after_or_equal:data.tanggal_mulai_kegiatan'],
                'data.tempat' => ['nullable', 'string', 'max:255'],
                'data.penanggung_jawab' => ['required', 'string', 'max:150'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.akun_id' => ['required', 'string', Rule::exists('akun', 'id')->where('aktif', true)->whereNull('deleted_at')],
                'items.*.uraian' => ['required', 'string', 'max:255'],
                'items.*.rincian_volume' => ['nullable', 'string', 'max:100'],
                'items.*.volume' => ['required', 'regex:'.self::POLA_DESIMAL],
                'items.*.satuan' => ['required', 'string', 'max:30'],
                'items.*.harga_satuan' => ['required', 'regex:'.self::POLA_DESIMAL],
                'items.*.keterangan' => ['nullable', 'string', 'max:255'],
            ],
            [
                'items.required' => 'Minimal satu item RAB.',
                'items.min' => 'Minimal satu item RAB.',
                'items.*.volume.regex' => 'Volume harus angka desimal positif (mis. 2 atau 0.5).',
                'items.*.harga_satuan.regex' => 'Harga satuan harus angka desimal tidak negatif.',
                'data.tanggal_selesai_kegiatan.after_or_equal' => 'Tanggal selesai kegiatan tidak boleh sebelum tanggal mulai.',
            ],
        )->after(function ($validator) use ($items): void {
            foreach (array_values($items) as $i => $item) {
                $volume = (string) ($item['volume'] ?? '');

                if (preg_match(self::POLA_DESIMAL, $volume) && Uang::bandingkan($volume, '0') <= 0) {
                    $validator->errors()->add("items.{$i}.volume", 'Volume harus lebih dari 0.');
                }
            }
        })->validate();

        $tahun = TahunAnggaran::query()->findOrFail($tervalidasi['data']['tahun_anggaran_id']);

        if (! in_array($tahun->status, [StatusTahunAnggaran::Perencanaan, StatusTahunAnggaran::Berjalan], true)) {
            throw new KesalahanDomain("Tahun anggaran {$tahun->tahun} sudah ditutup.");
        }

        $kolom = ['tahun_anggaran_id', 'kategori_penggunaan_dana_id', 'judul_kegiatan', 'deskripsi', 'tanggal_mulai_kegiatan', 'tanggal_selesai_kegiatan', 'tempat', 'penanggung_jawab'];
        $kolomItem = ['akun_id', 'uraian', 'rincian_volume', 'volume', 'satuan', 'harga_satuan', 'keterangan'];

        return [
            array_intersect_key($data, array_flip($kolom)),
            array_map(fn (array $item): array => array_intersect_key($item, array_flip($kolomItem)), array_values($items)),
        ];
    }
}
