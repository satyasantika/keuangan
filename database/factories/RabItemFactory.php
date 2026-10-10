<?php

namespace Database\Factories;

use App\Models\Akun;
use App\Models\Rab;
use App\Models\RabItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RabItem>
 */
class RabItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rab_id' => Rab::factory(),
            'akun_id' => Akun::factory(),
            'urutan' => 1,
            'uraian' => 'Konsumsi rapat',
            'rincian_volume' => '2 org x 1 hr',
            'volume' => '2.00',
            'satuan' => 'OH',
            'harga_satuan' => '150000.00',
            'jumlah' => '300000.00',
        ];
    }
}
