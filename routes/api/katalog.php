<?php

use App\Http\Controllers\KatalogController;
use App\Http\Controllers\ProdukSayaController;
use App\Http\Controllers\TokoSayaController;
use Illuminate\Support\Facades\Route;

// Katalog bersifat publik dan hanya-baca.
Route::get('kategori', [KatalogController::class, 'kategori'])->name('kategori.index');
Route::get('produk', [KatalogController::class, 'index'])->name('produk.index');
Route::get('produk/{slug}', [KatalogController::class, 'show'])->name('produk.show');
Route::get('toko/{slug}', [KatalogController::class, 'toko'])->name('toko.show');

// Toko dan produk milik pengguna yang sedang masuk.
Route::middleware('auth:sesi')->group(function (): void {
    Route::get('toko-saya', [TokoSayaController::class, 'show'])->name('toko-saya.show');
    Route::post('toko-saya', [TokoSayaController::class, 'store'])->name('toko-saya.store');
    Route::patch('toko-saya', [TokoSayaController::class, 'update'])->name('toko-saya.update');

    Route::get('toko-saya/produk', [ProdukSayaController::class, 'index'])->name('toko-saya.produk.index');
    Route::post('toko-saya/produk', [ProdukSayaController::class, 'store'])->name('toko-saya.produk.store');
    Route::patch('toko-saya/produk/{produk}', [ProdukSayaController::class, 'update'])->name('toko-saya.produk.update');
    Route::delete('toko-saya/produk/{produk}', [ProdukSayaController::class, 'destroy'])->name('toko-saya.produk.destroy');
});
