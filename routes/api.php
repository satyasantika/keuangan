<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)
    ->middleware('throttle:60,1')
    ->name('health');
