<?php

use App\Http\Controllers\PeranPenggunaController;
use App\Http\Controllers\RegistrasiController;
use App\Http\Controllers\StatusAkunController;
use App\Http\Controllers\UbahKataSandiController;
use Illuminate\Support\Facades\Route;

// Login, logout, dan daftar sesi menyusul pada Sesi 2.
// throttle:5,1,registrasi = 5 permintaan per 1 menit per alamat IP.
// Parameter ketiga memberi awalan kunci, sehingga hitungannya tidak
// tercampur dengan rute lain yang kelak memakai throttle.
Route::post('registrasi', RegistrasiController::class)
    ->middleware('throttle:5,1,registrasi')
    ->name('registrasi');

// Mengganti kata sandi adalah tindakan sensitif: hanya sesi segar yang boleh.
Route::middleware('auth:sesi')->group(function (): void {
    Route::put('saya/kata-sandi', UbahKataSandiController::class)
        ->middleware('sesi.segar')
        ->name('saya.kata-sandi');
});

// Pintu admin atas peran. Urutannya: kunci sah (401), memegang izin (403), barulah yang lain.
Route::middleware(['auth:sesi', 'izin:peran.kelola'])
    ->prefix('admin/pengguna')
    ->name('admin.pengguna.')
    ->whereNumber('id')
    ->group(function (): void {
        Route::get('/', [PeranPenggunaController::class, 'index'])->name('index');

        // Mengubah kewenangan orang lain adalah tindakan sensitif (LP-15).
        Route::middleware('sesi.segar')->group(function (): void {
            Route::put('{id}/peran/{peran}', [PeranPenggunaController::class, 'beri'])
                ->name('peran.beri');
            Route::delete('{id}/peran/{peran}', [PeranPenggunaController::class, 'cabut'])
                ->name('peran.cabut');
        });
    });

// Pintu admin atas status akun. Menangguhkan akun adalah tindakan sensitif (LP-15).
Route::middleware(['auth:sesi', 'izin:akun.tangguhkan', 'sesi.segar'])
    ->prefix('admin/pengguna')
    ->name('admin.pengguna.')
    ->whereNumber('id')
    ->group(function (): void {
        Route::post('{id}/tangguhkan', [StatusAkunController::class, 'tangguhkan'])
            ->name('tangguhkan');
        Route::post('{id}/pulihkan', [StatusAkunController::class, 'pulihkan'])
            ->name('pulihkan');
    });
