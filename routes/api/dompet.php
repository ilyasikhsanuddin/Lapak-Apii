<?php

use App\Http\Controllers\BukuBesarController;
use App\Http\Controllers\DompetController;
use App\Http\Controllers\PembayaranSayaController;
use App\Http\Controllers\PencairanSayaController;
use App\Http\Controllers\PencairanStafController;
use App\Http\Controllers\WebhookPembayaranController;
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
        Route::get('kelompok/{kelompok}', [BukuBesarController::class, 'kelompok'])->name('kelompok.show');
    });

// Tagihan milik pembeli yang sedang masuk. Pembeli hanya dapat MELIHAT tagihannya.
Route::get('pembayaran/{kode}', [PembayaranSayaController::class, 'show'])
    ->middleware(['auth:sesi', 'izin:pesanan.buat'])
    ->name('pembayaran.show');

// Satu-satunya rute tulis tanpa kunci sesi. Pemanggilnya gerbang pembayaran, dan yang
// menjaganya adalah tanda tangan HMAC di dalam controller. Terdaftar pada RUTE_PUBLIK.
Route::post('webhook/pembayaran', WebhookPembayaranController::class)->name('webhook.pembayaran');

// Pencairan routes for user actions (auth:sesi)
Route::middleware(['auth:sesi', 'izin:pencairan.ajukan', 'idempoten'])->group(function (): void {
    Route::post('dompet/pencairan', [PencairanSayaController::class, 'store'])->name('pencairan.store');
    Route::get('dompet/pencairan', [PencairanSayaController::class, 'index'])->name('pencairan.index');
});

// Pencairan routes for staff decisions (auth:sesi)
Route::middleware(['auth:sesi', 'izin:pencairan.setujui', 'idempoten'])->prefix('keuangan')->group(function (): void {
    Route::get('pencairan', [PencairanStafController::class, 'index'])->name('pencairan.staf.index');
    Route::post('pencairan/{kode}/setujui', [PencairanStafController::class, 'setujui'])->name('pencairan.staf.setujui');
    Route::post('pencairan/{kode}/tolak', [PencairanStafController::class, 'tolak'])->name('pencairan.staf.tolak');
});
