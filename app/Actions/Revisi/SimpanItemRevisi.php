<?php

namespace App\Actions\Revisi;

use App\Actions\Pengajuan\HitungTotalRab;
use App\Exceptions\KesalahanDomain;
use App\Models\Rab;
use App\Models\RabItem;
use App\Models\RealisasiItem;
use App\Models\User;
use App\Support\DenganKunci;
use App\Support\Uang;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Menyunting item revisi draf: ubah (asal tetap), tambah (asal null), hapus (BR-11).
 */
class SimpanItemRevisi
{
    private const POLA_DESIMAL = '/^\d+(\.\d{1,2})?$/';

    public function __construct(private readonly HitungTotalRab $hitungTotalRab) {}

    /**
     * @param  array<int, array<string, mixed>>  $items  tiap item boleh membawa 'id' item revisi yang sudah ada
     */
    public function handle(Rab $rab, array $items, User $oleh): Rab
    {
        if (! $oleh->can('update', $rab)) {
            throw new AuthorizationException('Revisi hanya dapat disunting oleh unitnya selama berstatus draf.');
        }

        $items = $this->validasi(array_values($items));

        return DenganKunci::jalankan("aksi:rab:{$rab->id}", fn (): Rab => DB::transaction(function () use ($rab, $items): Rab {
            $terkini = Rab::query()->lockForUpdate()->findOrFail($rab->getKey());
            $lama = RabItem::query()->where('rab_id', $terkini->id)->get()->keyBy('id');
            $dipertahankan = [];

            foreach ($items as $urutan => $data) {
                $id = $data['id'] ?? null;
                $item = $id !== null ? $lama->get($id) : null;

                if ($id !== null && $item === null) {
                    throw new KesalahanDomain('Item revisi tidak dikenal.');
                }

                $item ??= new RabItem(['rab_id' => $terkini->id, 'asal_rab_item_id' => null]);
                $item->fill([
                    'akun_id' => $data['akun_id'],
                    'urutan' => $urutan + 1,
                    'uraian' => $data['uraian'],
                    'rincian_volume' => $data['rincian_volume'] ?? null,
                    'volume' => $data['volume'],
                    'satuan' => $data['satuan'],
                    'harga_satuan' => $data['harga_satuan'],
                    'keterangan' => $data['keterangan'] ?? null,
                ])->save();

                $dipertahankan[] = $item->id;
            }

            foreach ($lama as $id => $item) {
                if (in_array($id, $dipertahankan, true)) {
                    continue;
                }

                if ($item->asal_rab_item_id !== null && RealisasiItem::query()->where('rab_item_id', $item->asal_rab_item_id)->exists()) {
                    throw new KesalahanDomain("Item \"{$item->uraian}\" sudah memiliki realisasi dan tidak boleh dihapus.");
                }

                $item->delete();
            }

            return $this->hitungTotalRab->handle($terkini);
        }));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function validasi(array $items): array
    {
        $hasil = Validator::make(['items' => $items], [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'string'],
            'items.*.akun_id' => ['required', 'string', Rule::exists('akun', 'id')->where('aktif', true)->whereNull('deleted_at')],
            'items.*.uraian' => ['required', 'string', 'max:255'],
            'items.*.rincian_volume' => ['nullable', 'string', 'max:100'],
            'items.*.volume' => ['required', 'regex:'.self::POLA_DESIMAL],
            'items.*.satuan' => ['required', 'string', 'max:30'],
            'items.*.harga_satuan' => ['required', 'regex:'.self::POLA_DESIMAL],
            'items.*.keterangan' => ['nullable', 'string', 'max:255'],
        ], ['items.required' => 'Minimal satu item RAB.', 'items.min' => 'Minimal satu item RAB.'])->validate();

        foreach ($hasil['items'] as $i => $item) {
            if (Uang::bandingkan((string) $item['volume'], '0') <= 0) {
                throw ValidationException::withMessages(["items.{$i}.volume" => 'Volume harus lebih dari 0.']);
            }
        }

        return $hasil['items'];
    }
}
