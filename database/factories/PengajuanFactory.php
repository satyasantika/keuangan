<?php

namespace Database\Factories;

use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use App\Models\TahunAnggaran;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pengajuan>
 */
class PengajuanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mulai = fake()->dateTimeBetween('2027-02-01', '2027-10-31');

        return [
            'tahun_anggaran_id' => fn () => TahunAnggaran::query()->latest('tahun')->value('id'),
            'unit_id' => Unit::factory(),
            'judul_kegiatan' => 'Kegiatan '.fake()->words(3, true),
            'deskripsi' => fake()->sentence(),
            'tanggal_mulai_kegiatan' => $mulai->format('Y-m-d'),
            'tanggal_selesai_kegiatan' => (clone $mulai)->modify('+2 days')->format('Y-m-d'),
            'tempat' => 'Kampus Unsil',
            'penanggung_jawab' => fake()->name(),
            'dibuat_oleh' => User::factory(),
            'status' => StatusPengajuan::Draf,
        ];
    }

    public function status(StatusPengajuan $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function diajukan(): static
    {
        return $this->state(fn () => [
            'status' => StatusPengajuan::Diajukan,
            'nomor' => 'PGJ/2027/'.fake()->unique()->numerify('9###'),
            'diajukan_pada' => now(),
        ]);
    }

    public function disetujui(): static
    {
        return $this->diajukan()->state(fn () => ['status' => StatusPengajuan::Disetujui, 'disetujui_pada' => now()]);
    }
}
