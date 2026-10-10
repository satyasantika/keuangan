<?php

use App\Http\Controllers\CetakKartuKegiatanController;
use App\Http\Controllers\CetakPengajuanController;
use App\Http\Controllers\CetakRevisiController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PanduanController;
use App\Http\Controllers\UnduhEksporController;
use App\Http\Controllers\VerifikasiPublikController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->to('/panel'));

Route::get('/verifikasi/{id}', VerifikasiPublikController::class)
    ->whereUuid('id')
    ->middleware('throttle:verifikasi-publik')
    ->name('verifikasi.pengajuan');

Route::middleware('auth')->group(function (): void {
    Route::get('/pengajuan/{pengajuan}/pdf', CetakPengajuanController::class)->name('pengajuan.pdf');
    Route::get('/pengajuan/{pengajuan}/kartu', CetakKartuKegiatanController::class)->name('pengajuan.kartu');
    Route::get('/revisi/{rab}/pdf', CetakRevisiController::class)->name('revisi.pdf');
    Route::get('/laporan/ekspor', [LaporanController::class, 'ekspor'])->middleware('throttle:ekspor')->name('laporan.ekspor');
    Route::get('/ekspor/{laporanEkspor}', UnduhEksporController::class)->middleware('signed')->name('ekspor.unduh');
    Route::get('/panduan-pengguna', PanduanController::class)->name('panduan.pengguna');
});
