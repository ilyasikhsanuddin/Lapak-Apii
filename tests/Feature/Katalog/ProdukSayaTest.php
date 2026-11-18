<?php

use App\Enums\StatusProduk;
use App\Enums\StatusToko;
use App\Models\Kategori;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;

it('membuka toko baru dengan status awal menunggu (LP-4)', function () {
    $budi = Pengguna::factory()->create();

    $this->sebagai($budi)
        ->postJson('/api/v1/toko-saya', [
            'nama' => 'Tenun Lurik Mas Budi',
            'kota' => 'Klaten',
            'deskripsi' => 'Kerajinan tenun lurik asli Pedan.',
            'ongkir_rupiah' => 12000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.nama', 'Tenun Lurik Mas Budi')
        ->assertJsonPath('data.slug', 'tenun-lurik-mas-budi')
        ->assertJsonPath('data.status', 'menunggu')
        ->assertJsonPath('data.terlihat_publik', false)
        ->assertJsonPath('data.ongkir.rupiah', 12000);
});

it('menolak pembukaan toko kedua oleh pengguna yang sudah punya toko (LP-3)', function () {
    $sri = Pengguna::factory()->create();
    Toko::factory()->for($sri, 'pemilik')->create();

    $this->sebagai($sri)
        ->postJson('/api/v1/toko-saya', [
            'nama' => 'Toko Cabang Bu Sri',
            'kota' => 'Surakarta',
        ])
        ->assertStatus(409)
        ->assertJsonPath('galat.kode', 'LP-3');
});

it('memperbarui profil toko milik sendiri', function () {
    $sri = Pengguna::factory()->create();
    Toko::factory()->for($sri, 'pemilik')->create([
        'nama' => 'Batik Lama',
        'kota' => 'Surakarta',
    ]);

    $this->sebagai($sri)
        ->patchJson('/api/v1/toko-saya', [
            'nama' => 'Batik Laweyan Baru',
            'ongkir_rupiah' => 15000,
        ])
        ->assertOk()
        ->assertJsonPath('data.nama', 'Batik Laweyan Baru')
        ->assertJsonPath('data.slug', 'batik-laweyan-baru')
        ->assertJsonPath('data.ongkir.rupiah', 15000);
});

it('mengelola produk milik sendiri termasuk draf yang tetap tersembunyi di katalog publik (LP-4)', function () {
    $sri = Pengguna::factory()->create();
    $toko = Toko::factory()->for($sri, 'pemilik')->create(['status' => StatusToko::Aktif]);
    $kategori = Kategori::factory()->create();

    // 1. Tambah produk baru sebagai draf
    $buat = $this->sebagai($sri)
        ->postJson('/api/v1/toko-saya/produk', [
            'kategori_id' => $kategori->id,
            'nama' => 'Selendang Batik Tulis',
            'deskripsi' => 'Pewarna alam.',
            'harga_rupiah' => 250000,
            'stok' => 5,
            'berat_gram' => 300,
            'status' => StatusProduk::Draf->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draf')
        ->assertJsonPath('data.terlihat_publik', false);

    $produkId = $buat->json('data.id');

    // 2. Muncul di daftar produk milik pemilik toko
    $this->sebagai($sri)
        ->getJson('/api/v1/toko-saya/produk')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $produkId);

    // 3. Tidak bocor ke katalog publik selama masih draf
    $this->getJson('/api/v1/produk')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    // 4. Ubah status menjadi aktif & perbarui stok
    $this->sebagai($sri)
        ->patchJson("/api/v1/toko-saya/produk/{$produkId}", [
            'status' => StatusProduk::Aktif->value,
            'stok' => 8,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'aktif')
        ->assertJsonPath('data.terlihat_publik', true)
        ->assertJsonPath('data.stok', 8);

    // 5. Kini tampil di katalog publik
    $this->getJson('/api/v1/produk')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // 6. Hapus produk milik sendiri
    $this->sebagai($sri)
        ->deleteJson("/api/v1/toko-saya/produk/{$produkId}")
        ->assertOk()
        ->assertJsonPath('data.terhapus', true);

    expect(Produk::find($produkId))->toBeNull();
});

it('menolak perubahan atau penghapusan produk milik toko orang lain', function () {
    $sri = Pengguna::factory()->create();
    Toko::factory()->for($sri, 'pemilik')->create();

    $produkOrangLain = Produk::factory()->create();

    $this->sebagai($sri)
        ->patchJson("/api/v1/toko-saya/produk/{$produkOrangLain->id}", [
            'harga_rupiah' => 1000,
        ])
        ->assertNotFound();

    $this->sebagai($sri)
        ->deleteJson("/api/v1/toko-saya/produk/{$produkOrangLain->id}")
        ->assertNotFound();
});
