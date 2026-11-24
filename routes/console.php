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

// LP-11: catatan Idempotency-Key lebih dari tujuh hari dibuang setiap dini hari.
Schedule::command('lapak:pangkas-idempotensi')->dailyAt('03:30')->withoutOverlapping();
