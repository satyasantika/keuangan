<?php

namespace Database\Factories;

use App\Models\Akun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Akun>
 */
class AkunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->numerify('52####'),
            'nama' => 'Belanja '.fake()->words(2, true),
            'sumber' => 'manual',
            'aktif' => true,
        ];
    }
}
