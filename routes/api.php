<?php

use App\Http\Controllers\DeviceEventController;
use Illuminate\Support\Facades\Route;

Route::post('/absensi', [DeviceEventController::class, 'store'])
    ->name('device-events.store')
    ->middleware('throttle:60,1');
