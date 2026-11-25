<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sesi yang sudah lama mati dibuang setiap dini hari.
Schedule::command('lapak:pangkas-sesi')->dailyAt('03:15');

// Catatan Idempotency-Key yang sudah lama tidak mungkin diulang lagi oleh kliennya.
Schedule::command('lapak:pangkas-idempotensi')->dailyAt('03:30');

// LP-17: rantai log audit dihitung ulang setiap dini hari. Pemeriksaan yang gagal tanpa ada
// yang diberi tahu sama saja dengan tidak memeriksa, maka kegagalannya masuk log tingkat kritis.
Schedule::command('lapak:verifikasi-audit')
    ->dailyAt('03:45')
    ->onFailure(fn () => Log::critical('LP-17: rantai log audit putus.'));

// Checkout yang melewati batas pembayaran diperiksa setiap 10 menit.
Schedule::command('lapak:batalkan-kedaluwarsa')
    ->everyTenMinutes()
    ->withoutOverlapping();

// LP-12: selesaikan pesanan yang melewati masa penahanan dana.
Schedule::command('lapak:selesaikan-otomatis')->hourly()->withoutOverlapping();
