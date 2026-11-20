<?php

use App\Enums\StatusProduk;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;

it('menyaring daftar produk sendiri menurut status dan pencarian kata kunci', function () {
    $sri = Pengguna::factory()->penjual()->create();
    $toko = Toko::factory()->for($sri, 'pemilik')->create();

    Produk::factory()->for($toko)->create([
        'nama' => 'Kain Batik Parang',
        'status' => StatusProduk::Aktif,
    ]);
    Produk::factory()->for($toko)->create([
        'nama' => 'Selendang Batik Draf',
        'status' => StatusProduk::Draf,
    ]);
    Produk::factory()->for($toko)->create([
        'nama' => 'Kebaya Lurik Lawas',
        'status' => StatusProduk::Arsip,
    ]);

    // Saring menurut status saja
    $this->sebagai($sri)
        ->getJson('/api/v1/toko-saya/produk?status=draf')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama', 'Selendang Batik Draf');

    // Saring gabungan status dan kata kunci cari
    $this->sebagai($sri)
        ->getJson('/api/v1/toko-saya/produk?status=aktif&cari=Parang')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama', 'Kain Batik Parang');
});

it('menolak nilai status di luar daftar enum yang sah', function () {
    $sri = Pengguna::factory()->penjual()->create();
    Toko::factory()->for($sri, 'pemilik')->create();

    $this->sebagai($sri)
        ->getJson('/api/v1/toko-saya/produk?status=tidak_valid')
        ->assertUnprocessable();
});

it('memperlakukan karakter pengganti pada pencarian produk sendiri sebagai teks biasa', function () {
    $sri = Pengguna::factory()->penjual()->create();
    $toko = Toko::factory()->for($sri, 'pemilik')->create();

    Produk::factory()->for($toko)->create(['nama' => 'Diskon 50% Batik Tulis']);
    Produk::factory()->for($toko)->create(['nama' => 'Kain_Sutra_Alami']);
    Produk::factory()->for($toko)->create(['nama' => 'Selendang Polos']);

    // Karakter % tidak boleh mencocokkan semua produk
    $this->sebagai($sri)
        ->getJson('/api/v1/toko-saya/produk?cari=%25')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama', 'Diskon 50% Batik Tulis');

    // Karakter _ tidak boleh bertindak sebagai wildcard satu karakter
    $this->sebagai($sri)
        ->getJson('/api/v1/toko-saya/produk?cari=_Sutra_')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama', 'Kain_Sutra_Alami');
});
