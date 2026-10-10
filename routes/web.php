<?php

use App\Http\Controllers\CetakPengajuanController;
use App\Http\Controllers\CetakRevisiController;
use App\Http\Controllers\VerifikasiPublikController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->to('/panel'));

Route::get('/verifikasi/{id}', VerifikasiPublikController::class)
    ->whereUuid('id')
    ->middleware('throttle:verifikasi-publik')
    ->name('verifikasi.pengajuan');

Route::middleware('auth')->group(function (): void {
    Route::get('/pengajuan/{pengajuan}/pdf', CetakPengajuanController::class)->name('pengajuan.pdf');
    Route::get('/revisi/{rab}/pdf', CetakRevisiController::class)->name('revisi.pdf');
});
