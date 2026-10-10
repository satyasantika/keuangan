<?php

use App\Concerns\MenjagaPeriodeTerkunci;

/**
 * BR-19: semua model transaksi bertanggal dijaga periode terkunci.
 */
test('model transaksi bertanggal memakai MenjagaPeriodeTerkunci', function () {
    $model = [
        'App\Models\Pencairan',
        'App\Models\RealisasiItem',
        'App\Models\PotonganPajak',
        'App\Models\Pengembalian',
        'App\Models\RekapHonorEksternal',
    ];

    $diperiksa = 0;

    foreach ($model as $kelas) {
        if (! class_exists($kelas)) {
            continue; // RekapHonorEksternal dibuat pada F11.5.
        }

        expect(class_uses_recursive($kelas))->toHaveKey(MenjagaPeriodeTerkunci::class);
        $diperiksa++;
    }

    expect($diperiksa)->toBeGreaterThanOrEqual(4);
});
