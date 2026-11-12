<?php

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\Toko;

it('menampilkan daftar kategori beserta jumlah produk aktif menurut LP-4', function () {
    $kategori = Kategori::factory()->create(['nama' => 'Batik']);

    // Produk 1: Aktif & Toko Aktif (dihitung)
    Produk::factory()->for($kategori)->create();

    // Produk 2: Draf & Toko Aktif (TIDAK dihitung)
    Produk::factory()->for($kategori)->draf()->create();

    // Produk 3: Aktif tapi Toko Menunggu (TIDAK dihitung)
    Produk::factory()->for($kategori)->for(Toko::factory()->menunggu())->create();

    $this->getJson('/api/v1/kategori')
        ->assertOk()
        ->assertJsonPath('data.0.id', $kategori->id)
        ->assertJsonPath('data.0.jumlah_produk', 1);
});

it('mengembalikan jumlah produk nol jika seluruh produk dalam kategori berasal dari toko belum aktif (Latihan 4)', function () {
    $kategori = Kategori::factory()->create();
    Produk::factory()->for($kategori)->for(Toko::factory()->menunggu())->count(3)->create();

    $this->getJson('/api/v1/kategori')
        ->assertOk()
        ->assertJsonPath('data.0.jumlah_produk', 0);
});
