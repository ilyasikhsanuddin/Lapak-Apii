<?php

use App\Enums\StatusProduk;
use App\Enums\StatusToko;
use App\Models\Kategori;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;

it('membuka toko baru dengan status awal menunggu (LP-4)', function () {
    $budi = Pengguna::factory()->pembeli()->create();

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

it('menolak pembukaan toko oleh akun yang tidak memegang izin toko.buka', function (string $pemegang) {
    $this->sebagai(Pengguna::factory()->{$pemegang}()->create())
        ->postJson('/api/v1/toko-saya', [
            'nama' => 'Toko Staf',
            'kota' => 'Surakarta',
            'ongkir_rupiah' => 10_000,
        ])
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');

    expect(Toko::count())->toBe(0);
})->with(['admin', 'keuangan', 'auditor']);

it('menolak pembukaan toko kedua oleh pengguna yang sudah punya toko (LP-3)', function () {
    $sri = Pengguna::factory()->penjual()->create();
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
    $sri = Pengguna::factory()->penjual()->create();
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

it('membolehkan pemilik toko yang masih menunggu membenahi datanya', function () {
    $tini = Pengguna::factory()->pembeli()->create();
    Toko::factory()->menunggu()->for($tini, 'pemilik')->create();

    $this->sebagai($tini)->patchJson('/api/v1/toko-saya', ['kota' => 'Karanganyar'])
        ->assertOk()
        ->assertJsonPath('data.kota', 'Karanganyar')
        ->assertJsonPath('data.status', 'menunggu');
});

it('menolak pemilik toko yang belum disetujui: ia belum menjadi penjual', function () {
    $tini = Pengguna::factory()->pembeli()->create();
    Toko::factory()->menunggu()->for($tini, 'pemilik')->create();
    $kategori = Kategori::factory()->create();

    $this->sebagai($tini)
        ->postJson('/api/v1/toko-saya/produk', [
            'kategori_id' => $kategori->id,
            'nama' => 'Wedang Uwuh',
            'harga_rupiah' => 25000,
            'stok' => 10,
            'berat_gram' => 200,
            'status' => StatusProduk::Aktif->value,
        ])
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');

    $this->getJson('/api/v1/toko-saya/produk')->assertForbidden();
    expect(Produk::count())->toBe(0);
});

it('mengelola produk milik sendiri termasuk draf yang tetap tersembunyi di katalog publik (LP-4)', function () {
    $sri = Pengguna::factory()->penjual()->create();
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

    // 4. Ubah status menjadi aktif & perbarui stok lewat pintu penyesuaian stok
    $this->sebagai($sri)
        ->patchJson("/api/v1/toko-saya/produk/{$produkId}", [
            'status' => StatusProduk::Aktif->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'aktif')
        ->assertJsonPath('data.terlihat_publik', true);

    $this->sebagai($sri)
        ->withHeader('Idempotency-Key', 'ubah-stok-produk-saya-1')
        ->postJson("/api/v1/toko-saya/produk/{$produkId}/stok", [
            'selisih' => 3,
        ])
        ->assertOk()
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

it('tetap membolehkan penjual mengelola produk selagi tokonya ditangguhkan', function () {
    $sri = Pengguna::factory()->penjual()->create();
    $toko = Toko::factory()->for($sri, 'pemilik')->create(['status' => StatusToko::Ditangguhkan]);
    $kategori = Kategori::factory()->create();

    $id = $this->sebagai($sri)
        ->postJson('/api/v1/toko-saya/produk', [
            'kategori_id' => $kategori->id,
            'nama' => 'Kain Batik Sogan',
            'harga_rupiah' => 185000,
            'stok' => 10,
            'berat_gram' => 300,
            'status' => StatusProduk::Aktif->value,
        ])
        ->assertCreated()
        ->json('data.id');

    // Boleh dikelola, tetapi tidak tampil: yang menutupnya status toko, bukan izin (LP-4).
    expect(Produk::find($id))->not->toBeNull();
    $this->tanpaKunci()->getJson('/api/v1/produk')->assertJsonCount(0, 'data');
});

it('menolak perubahan atau penghapusan produk milik toko orang lain', function () {
    $sri = Pengguna::factory()->penjual()->create();
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
