<?php

use App\Models\Keranjang;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;

beforeEach(function () {
    $this->toko = Toko::factory()->create([
        'nama' => 'Batik Bu Sri',
        'ongkir_rupiah' => 12_000,
    ]);
});

it('menolak pengguna yang belum masuk', function () {
    $this->getJson('/api/v1/keranjang')
        ->assertUnauthorized()
        ->assertJsonPath('galat.kode', 'BELUM_MASUK');
});

it('menampilkan keranjang kosong untuk pengguna yang sudah masuk', function () {
    $pengguna = Pengguna::factory()->create();

    $this->sebagai($pengguna)
        ->getJson('/api/v1/keranjang')
        ->assertOk()
        ->assertJsonPath('data.pesanan', [])
        ->assertJsonPath('data.total_bayar.rupiah', 0)
        ->assertJsonPath('data.total_bayar.tampilan', 'Rp 0')
        ->assertJsonPath('data.tidak_tersedia', []);
});

it('menampilkan rincian produk valid dan memisahkan butir bermasalah', function () {
    $pengguna = Pengguna::factory()->create();

    $valid = Produk::factory()->for($this->toko)->create([
        'nama' => 'Kain Batik Cap',
        'harga_rupiah' => 185_000,
        'stok' => 5,
    ]);

    $draf = Produk::factory()->for($this->toko)->draf()->create([
        'nama' => 'Batik Draf',
    ]);

    $stokTerbatas = Produk::factory()->for($this->toko)->create([
        'nama' => 'Batik Stok Terbatas',
        'harga_rupiah' => 50_000,
        'stok' => 1,
    ]);

    $keranjang = Keranjang::query()->forceCreate([
        'pengguna_id' => $pengguna->id,
    ]);

    $keranjang->butir()->createMany([
        ['produk_id' => $valid->id, 'kuantitas' => 2],
        ['produk_id' => $draf->id, 'kuantitas' => 1],
        ['produk_id' => $stokTerbatas->id, 'kuantitas' => 3],
    ]);

    $this->sebagai($pengguna)
        ->getJson('/api/v1/keranjang')
        ->assertOk()
        ->assertJsonPath('data.pesanan.0.toko', 'Batik Bu Sri')
        ->assertJsonPath('data.pesanan.0.baris.0.nama', 'Kain Batik Cap')
        ->assertJsonPath('data.pesanan.0.baris.0.kuantitas', 2)
        ->assertJsonPath('data.pesanan.0.subtotal', 370_000)
        ->assertJsonPath('data.total_bayar.rupiah', 382_000)
        ->assertJsonCount(2, 'data.tidak_tersedia')
        ->assertJsonPath('data.tidak_tersedia.0.produk_id', $draf->id)
        ->assertJsonPath('data.tidak_tersedia.0.alasan', 'tidak_tersedia')
        ->assertJsonPath('data.tidak_tersedia.1.produk_id', $stokTerbatas->id)
        ->assertJsonPath('data.tidak_tersedia.1.alasan', 'stok_kurang')
        ->assertJsonPath('data.tidak_tersedia.1.stok_tersedia', 1);
});
