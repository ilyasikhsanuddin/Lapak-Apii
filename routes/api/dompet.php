<?php

use App\Http\Controllers\BukuBesarController;
use App\Http\Controllers\DompetController;
use Illuminate\Support\Facades\Route;

// Dompet pribadi: hanya pengguna yang memiliki izin dompet.lihat.
Route::middleware(['auth:sesi', 'izin:dompet.lihat'])->group(function (): void {
    Route::get('dompet', [DompetController::class, 'show'])->name('dompet.show');

    // Riwayat uang memerlukan sesi segar (LP-15).
    Route::get('dompet/mutasi', [DompetController::class, 'mutasi'])
        ->middleware('sesi.segar')
        ->name('dompet.mutasi');
});

// Buku besar seluruh marketplace: hanya staf pemegang izin buku-besar.lihat.
// Admin tidak otomatis memiliki izin ini. Seluruh rute di sini hanya membaca.
Route::middleware(['auth:sesi', 'izin:buku-besar.lihat', 'sesi.segar'])
    ->prefix('buku-besar')
    ->name('buku-besar.')
    ->whereNumber('id')
    ->group(function (): void {
        Route::get('dompet', [BukuBesarController::class, 'index'])->name('dompet.index');
        Route::get('dompet/{id}/mutasi', [BukuBesarController::class, 'mutasi'])->name('dompet.mutasi');
    });
