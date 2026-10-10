<?php

namespace Database\Factories;

use App\Models\RabItem;
use App\Models\RealisasiItem;
use App\Models\Spj;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Membutuhkan tahun anggaran berperiode untuk tanggal_bukti.
 *
 * @extends Factory<RealisasiItem>
 */
class RealisasiItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spj_id' => Spj::factory(),
            'rab_item_id' => RabItem::factory(),
            'tanggal_bukti' => '2027-06-10',
            'nomor_bukti' => fake()->bothify('KW-####'),
            'uraian' => 'Konsumsi peserta',
            'volume' => '1.00',
            'harga_satuan' => '100000.00',
            'jumlah_bruto' => '100000.00',
            'total_pajak' => '0.00',
            'jumlah_neto' => '100000.00',
            'dibuat_oleh' => User::factory(),
        ];
    }
}
