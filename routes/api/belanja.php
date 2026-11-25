<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\KeranjangController;
use App\Http\Controllers\PesananSayaController;
use App\Http\Controllers\SimulasiBelanjaController;
use Illuminate\Support\Facades\Route;

Route::post('simulasi-belanja', SimulasiBelanjaController::class)
    ->name('belanja.simulasi');

Route::middleware(['auth:sesi', 'izin:keranjang.kelola'])
    ->prefix('keranjang')
    ->name('keranjang.')
    ->group(function (): void {
        Route::get('/', [KeranjangController::class, 'show'])
            ->name('show');

        Route::delete('/', [KeranjangController::class, 'kosongkan'])
            ->name('kosongkan');

        Route::put('butir/{produkId}', [KeranjangController::class, 'simpanButir'])
            ->whereNumber('produkId')
            ->name('simpan');

        Route::delete('butir/{produkId}', [KeranjangController::class, 'hapusButir'])
            ->whereNumber('produkId')
            ->name('hapus');
    });

Route::middleware(['auth:sesi', 'izin:pesanan.buat'])
    ->group(function (): void {
        Route::post('checkout', CheckoutController::class)
            ->middleware(['throttle:transaksi', 'idempoten'])
            ->name('checkout');

        Route::get('pesanan', [PesananSayaController::class, 'index'])
            ->name('pesanan.index');

        Route::get('pesanan/{nomor}', [PesananSayaController::class, 'show'])
            ->name('pesanan.show');

        // Tindakan pembeli: wajib batas laju dan Idempotency-Key.
        Route::middleware(['throttle:transaksi', 'idempoten'])->group(function (): void {
            Route::post('pesanan/{nomor}/terima', [PesananSayaController::class, 'terima'])
                ->name('pesanan.terima');

            Route::post('pesanan/{nomor}/batal', [PesananSayaController::class, 'batal'])
                ->name('pesanan.batal');

            Route::post('checkout/{kode}/batal', [PesananSayaController::class, 'batalCheckout'])
                ->name('checkout.batal');
        });
    });
