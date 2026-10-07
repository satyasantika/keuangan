<?php

test('zona waktu aplikasi adalah Asia/Jakarta', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta');
});

test('locale aplikasi adalah id', function () {
    expect(config('app.locale'))->toBe('id');
});
