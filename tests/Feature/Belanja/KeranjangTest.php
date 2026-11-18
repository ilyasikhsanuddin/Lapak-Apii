<?php

use App\Models\ButirKeranjang;
use App\Models\Keranjang;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;

beforeEach(function () {
    $this->andi = Pengguna::factory()->create();

    $this->toko = Toko::factory()->create([
        'nama' => 'Batik Bu Sri',
        'ongkir_rupiah' => 12_000,
    ]);

    $this->batik = $this->toko;

    $this->kain = Produk::factory()->for($this->batik)->create([
        'nama' => 'Kain Batik Cap Sogan',
        'harga_rupiah' => 185_000,
        'stok' => 5,
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

it('menaruh butir dan menetapkan kuantitasnya, bukan menambahkannya', function () {
    $alamat = "/api/v1/keranjang/butir/{$this->kain->id}";

    $this->sebagai($this->andi)
        ->putJson($alamat, ['kuantitas' => 2])
        ->assertOk()
        ->assertJsonPath('data.total_bayar.rupiah', 382_000);

    $this->putJson($alamat, ['kuantitas' => 2])
        ->assertJsonPath('data.total_bayar.rupiah', 382_000);

    expect(ButirKeranjang::sole()->kuantitas)->toBe(2);
});

it('menyimpan dan menampilkan catatan pada butir keranjang', function () {
    $alamat = "/api/v1/keranjang/butir/{$this->kain->id}";

    $this->sebagai($this->andi)
        ->putJson($alamat, [
            'kuantitas' => 2,
            'catatan' => 'Jangan bungkus terlalu rapat.',
        ])
        ->assertOk()
        ->assertJsonPath(
            'data.pesanan.0.baris.0.catatan',
            'Jangan bungkus terlalu rapat.',
        );

    expect(ButirKeranjang::sole()->catatan)
        ->toBe('Jangan bungkus terlalu rapat.');

    $this->getJson('/api/v1/keranjang')
        ->assertOk()
        ->assertJsonPath(
            'data.pesanan.0.baris.0.catatan',
            'Jangan bungkus terlalu rapat.',
        );
});

it('menolak catatan yang melebihi 200 karakter', function () {
    $this->sebagai($this->andi)
        ->putJson("/api/v1/keranjang/butir/{$this->kain->id}", [
            'kuantitas' => 1,
            'catatan' => str_repeat('a', 201),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'VALIDASI');
});

it('tidak menyimpan harga kiriman klien di mana pun (LP-6)', function () {
    $this->sebagai($this->andi)
        ->putJson("/api/v1/keranjang/butir/{$this->kain->id}", [
            'kuantitas' => 1,
            'harga_satuan' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.pesanan.0.baris.0.harga_satuan', 185_000);
});

it('menolak penjual yang memasukkan produk tokonya sendiri (LP-5)', function () {
    $sri = $this->batik->pemilik;

    $this->sebagai($sri)
        ->putJson("/api/v1/keranjang/butir/{$this->kain->id}", ['kuantitas' => 1])
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'LP-5');

    expect(ButirKeranjang::count())->toBe(0);

    $produkLain = Produk::factory()->create(['stok' => 3]);

    $this->putJson("/api/v1/keranjang/butir/{$produkLain->id}", ['kuantitas' => 1])
        ->assertOk();
});

it('menolak produk yang tidak tampil dengan jawaban yang sama untuk semua sebabnya (LP-4)', function () {
    $draf = Produk::factory()->for($this->batik)->draf()->create();

    $jawabanDraf = $this->sebagai($this->andi)
        ->putJson("/api/v1/keranjang/butir/{$draf->id}", ['kuantitas' => 1])
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'LP-4');

    $jawabanTakAda = $this->putJson('/api/v1/keranjang/butir/999999', ['kuantitas' => 1])
        ->assertUnprocessable();

    expect($jawabanDraf->json('galat.pesan'))
        ->toBe($jawabanTakAda->json('galat.pesan'));
});

it('menolak kuantitas melebihi stok dengan status 409 (LP-4)', function () {
    $this->sebagai($this->andi)
        ->putJson("/api/v1/keranjang/butir/{$this->kain->id}", ['kuantitas' => 6])
        ->assertConflict()
        ->assertJsonPath('galat.rincian.tersedia', 5);
});

it('menolak kuantitas yang tidak masuk akal', function (mixed $kuantitas) {
    $this->sebagai($this->andi)
        ->putJson("/api/v1/keranjang/butir/{$this->kain->id}", ['kuantitas' => $kuantitas])
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'VALIDASI');
})->with([
    'nol' => [0],
    'negatif' => [-2],
    'pecahan' => [1.5],
    'terlalu banyak' => [100],
    'teks' => ['dua'],
]);

it('membatasi jumlah produk berbeda dalam satu keranjang', function () {
    config(['lapak.belanja.butir_maksimum' => 2]);

    $lain = Produk::factory()->count(2)->create(['stok' => 5]);

    $taruh = fn (Produk $produk, int $kuantitas) => $this->putJson(
        "/api/v1/keranjang/butir/{$produk->id}",
        ['kuantitas' => $kuantitas],
    );

    $this->sebagai($this->andi);

    $taruh($this->kain, 1)->assertOk();
    $taruh($lain[0], 1)->assertOk();

    $taruh($lain[1], 1)
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'KERANJANG_PENUH');

    $taruh($this->kain, 3)->assertOk();
});

it('menghapus butir dan mengosongkan keranjang tanpa menyentuh keranjang orang lain', function () {
    $dewi = Pengguna::factory()->create();

    $isi = function (Pengguna $pengguna, Produk $produk, int $kuantitas): void {
        $keranjang = Keranjang::query()
            ->where('pengguna_id', $pengguna->id)
            ->first();

        if ($keranjang === null) {
            $keranjang = new Keranjang;
            $keranjang->pengguna_id = $pengguna->id;
            $keranjang->save();
        }

        $keranjang->butir()->create([
            'produk_id' => $produk->id,
            'kuantitas' => $kuantitas,
        ]);
    };

    $isi($dewi, $this->kain, 1);
    $isi($this->andi, $this->kain, 2);

    $this->sebagai($this->andi)
        ->deleteJson("/api/v1/keranjang/butir/{$this->kain->id}")
        ->assertOk()
        ->assertJsonPath('data.pesanan', []);

    $this->deleteJson("/api/v1/keranjang/butir/{$this->kain->id}")
        ->assertOk();

    $this->deleteJson('/api/v1/keranjang')
        ->assertOk();

    expect(ButirKeranjang::count())->toBe(1);

    $this->sebagai($dewi)
        ->getJson('/api/v1/keranjang')
        ->assertJsonPath('data.total_bayar.rupiah', 197_000);
});
