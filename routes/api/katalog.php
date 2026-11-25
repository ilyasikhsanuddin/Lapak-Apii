<?php

use App\Http\Controllers\KatalogController;
use App\Http\Controllers\ModerasiTokoController;
use App\Http\Controllers\PesananTokoController;
use App\Http\Controllers\ProdukSayaController;
use App\Http\Controllers\TokoSayaController;
use Illuminate\Support\Facades\Route;

// Katalog bersifat publik dan hanya-baca.
Route::get('kategori', [KatalogController::class, 'kategori'])->name('kategori.index');
Route::get('produk', [KatalogController::class, 'index'])->name('produk.index');
Route::get('produk/{slug}', [KatalogController::class, 'show'])->name('produk.show');
Route::get('toko/{slug}', [KatalogController::class, 'toko'])->name('toko.show');

// Toko dan produk milik pengguna yang sedang masuk.
// Alamatnya tidak memuat pengenal toko: toko selalu ditentukan sesi.
Route::middleware('auth:sesi')
    ->prefix('toko-saya')
    ->name('toko-saya.')
    ->group(function (): void {
        Route::get('/', [TokoSayaController::class, 'show'])->name('show');
        Route::patch('/', [TokoSayaController::class, 'update'])->name('update');

        Route::post('/', [TokoSayaController::class, 'store'])
            ->middleware('izin:toko.buka')
            ->name('store');

        // Produk baru dapat dikelola setelah toko disetujui dan pemiliknya menjadi penjual.
        Route::middleware('izin:produk.kelola')->group(function (): void {
            Route::get('produk', [ProdukSayaController::class, 'index'])->name('produk.index');
            Route::post('produk', [ProdukSayaController::class, 'store'])->name('produk.store');
            Route::patch('produk/{produk}', [ProdukSayaController::class, 'update'])->name('produk.update');
            Route::post('produk/{id}/stok', [ProdukSayaController::class, 'stok'])->whereNumber('id')->middleware('idempoten')->name('produk.stok');
            Route::delete('produk/{produk}', [ProdukSayaController::class, 'destroy'])
                ->name('produk.destroy');
        });

        // Pesanan yang masuk ke toko sendiri. Izin pesanan.proses lahir bersama rute ini.
        Route::middleware('izin:pesanan.proses')
            ->prefix('pesanan')
            ->name('pesanan.')
            ->group(function (): void {
                Route::get('/', [PesananTokoController::class, 'index'])->name('index');
                Route::get('{nomor}', [PesananTokoController::class, 'show'])->name('show');

                // Tanpa Idempotency-Key: keduanya hanya memindahkan status. Pengulangan dijawab 409.
                Route::post('{nomor}/kemas', [PesananTokoController::class, 'kemas'])->name('kemas');
                Route::post('{nomor}/kirim', [PesananTokoController::class, 'kirim'])->name('kirim');

                // Dengan Idempotency-Key (LP-11): membatalkan mengembalikan stok dan uang.
                Route::post('{nomor}/batal', [PesananTokoController::class, 'batal'])
                    ->middleware('idempoten')
                    ->name('batal');
            });
    });

// Pintu admin atas toko.
Route::middleware(['auth:sesi', 'izin:toko.moderasi'])
    ->prefix('admin/toko')
    ->name('admin.toko.')
    ->whereNumber('id')
    ->group(function (): void {
        Route::get('/', [ModerasiTokoController::class, 'index'])->name('index');

        // Keputusan moderasi toko adalah tindakan sensitif (LP-15).
        Route::middleware('sesi.segar')->group(function (): void {
            Route::post('{id}/setujui', [ModerasiTokoController::class, 'setujui'])->name('setujui');
            Route::post('{id}/tolak', [ModerasiTokoController::class, 'tolak'])->name('tolak');
            Route::post('{id}/tangguhkan', [ModerasiTokoController::class, 'tangguhkan'])
                ->name('tangguhkan');
        });
    });
