<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sesi yang sudah lama mati dibuang setiap dini hari.
Schedule::command('lapak:pangkas-sesi')->dailyAt('03:15');

// Checkout yang melewati batas pembayaran diperiksa setiap 10 menit.
Schedule::command('lapak:batalkan-kedaluwarsa')
    ->everyTenMinutes()
    ->withoutOverlapping();

// LP-12: selesaikan pesanan yang melewati masa penahanan dana.
Schedule::command('lapak:selesaikan-otomatis')->hourly()->withoutOverlapping();
