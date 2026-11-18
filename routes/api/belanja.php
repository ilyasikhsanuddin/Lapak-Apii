<?php

use App\Http\Controllers\KeranjangController;
use App\Http\Controllers\SimulasiBelanjaController;
use Illuminate\Support\Facades\Route;

Route::post('simulasi-belanja', SimulasiBelanjaController::class)
    ->name('belanja.simulasi');

Route::middleware('auth:sesi')
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
