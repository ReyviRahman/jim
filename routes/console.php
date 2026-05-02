<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cron job: Cek membership expired setiap tengah malam (Asia/Jakarta)
Schedule::command('memberships:check-expired')
    ->daily()
    ->at('04:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/check-expired-memberships.log'));

// Cron job: Sinkronisasi stok awal minuman jam 00:01 (Asia/Jakarta)
Schedule::command('beverages:sync-stock init')
    ->daily()
    ->at('00:01')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/sync-beverage-stock.log'));

// Cron job: Sinkronisasi stok akhir minuman jam 23:59 (Asia/Jakarta)
Schedule::command('beverages:sync-stock last')
    ->daily()
    ->at('23:59')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/sync-beverage-stock.log'));
