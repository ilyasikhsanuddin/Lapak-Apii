<?php

use App\Enums\JenisDompet;
use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;

/*
 * Uji asap seeder (Latihan Mandiri 2, D5).
 *
 * Menjalankan DatabaseSeeder dari basis data kosong, lalu memastikan jumlah baris.
 * Angka di bawah mengikuti seeder pada modul:
 *   - PenggunaSeeder : 9 akun contoh
 *   - KatalogSeeder  : 4 kategori, 4 toko, 13 produk (4 + 4 + 3 + 2)
 *   - DompetSeeder   : 3 dompet sistem (eksternal, escrow, pendapatan)
 *
 * Bila seseorang mengubah data seeder dengan sengaja, ubah angka di sini pada PR yang sama.
 * Bila satu baris $this->call() di DatabaseSeeder hilang (skenario Langkah 18), uji ini merah.
 */

it('menjalankan seluruh seeder dengan jumlah baris yang sesuai', function () {
    $this->seed();

    expect(Pengguna::count())->toBe(9)
        ->and(Kategori::count())->toBe(4)
        ->and(Toko::count())->toBe(4)
        ->and(Produk::count())->toBe(13)
        ->and(Dompet::count())->toBe(3);
});

it('hanya membuat dompet sistem, tanpa dompet penjual', function () {
    $this->seed();

    expect(Dompet::where('jenis', JenisDompet::Eksternal)->count())->toBe(1)
        ->and(Dompet::where('jenis', JenisDompet::Escrow)->count())->toBe(1)
        ->and(Dompet::where('jenis', JenisDompet::Pendapatan)->count())->toBe(1)
        ->and(Dompet::where('jenis', JenisDompet::Penjual)->count())->toBe(0);
});

it('idempoten: seeder dijalankan dua kali tidak menggandakan baris', function () {
    $this->seed();
    $this->seed();

    expect(Pengguna::count())->toBe(9)
        ->and(Toko::count())->toBe(4)
        ->and(Produk::count())->toBe(13)
        ->and(Dompet::count())->toBe(3);
});
