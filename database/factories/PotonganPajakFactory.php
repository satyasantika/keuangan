<?php

namespace Database\Factories;

use App\Models\JenisPajak;
use App\Models\PotonganPajak;
use App\Models\RealisasiItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PotonganPajak>
 */
class PotonganPajakFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'realisasi_item_id' => RealisasiItem::factory(),
            'jenis_pajak_id' => fn () => JenisPajak::firstOrCreate(['kode' => 'pph23'], ['nama' => 'PPh Pasal 23'])->id,
            'dasar_pengenaan' => '100000.00',
            'nilai' => '2000.00',
            'dicatat_oleh' => User::factory(),
        ];
    }
}
