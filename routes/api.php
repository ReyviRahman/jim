<?php

use App\Http\Controllers\DeviceEventController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/devices/HQ-BIO-01/event', function (Request $request) {
    return app(DeviceEventController::class)->store($request, 'HQ-BIO-01');
})
    ->name('device-events.store')
    ->middleware('throttle:60,1');
