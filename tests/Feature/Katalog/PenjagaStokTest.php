<?php

declare(strict_types=1);

use App\Enums\StatusProduk;
use App\Enums\StatusToko;
use App\Exceptions\ProdukTidakDapatDibeli;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\PenjagaStok;

test('mengambil produk beserta tokonya terindeks nomor produk', function (): void {
    $produkA = Produk::factory()->create(['stok' => 5]);
    $produkB = Produk::factory()->create(['stok' => 10]);

    $hasil = (new PenjagaStok)->ambil([$produkA->id, $produkB->id]);

    expect($hasil)->toHaveCount(2)
        ->and($hasil->keys()->all())->toBe([$produkA->id, $produkB->id])
        ->and($hasil[$produkA->id]->relationLoaded('toko'))->toBeTrue();
});

test('mengurangi stok sampai habis menjadi nol', function (): void {
    $produk = Produk::factory()->create(['stok' => 3]);
    $penjaga = new PenjagaStok;

    $diambil = $penjaga->ambil([$produk->id])[$produk->id];
    $penjaga->kurangi($diambil, 3);

    expect($produk->fresh()->stok)->toBe(0);
});

test('menolak pengurangan bila stok tidak mencukupi dengan kode LP-4', function (): void {
    $produk = Produk::factory()->create(['stok' => 2]);
    $penjaga = new PenjagaStok;

    $diambil = $penjaga->ambil([$produk->id])[$produk->id];

    expect(fn () => $penjaga->kurangi($diambil, 3))
        ->toThrow(ProdukTidakDapatDibeli::class);

    expect($produk->fresh()->stok)->toBe(2);
});

test('menolak kuantitas kurang dari satu pada kurangi dan kembalikan', function (): void {
    $produk = Produk::factory()->create(['stok' => 5]);
    $penjaga = new PenjagaStok;
    $diambil = $penjaga->ambil([$produk->id])[$produk->id];

    expect(fn () => $penjaga->kurangi($diambil, 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $penjaga->kembalikan($produk->id, 0))->toThrow(InvalidArgumentException::class);
});

test('mengembalikan stok menambahkan kuantitas secara langsung', function (): void {
    $produk = Produk::factory()->create(['stok' => 2]);

    (new PenjagaStok)->kembalikan($produk->id, 4);

    expect($produk->fresh()->stok)->toBe(6);
});

test('sedangTampil memeriksa status produk dan status toko', function (): void {
    $tokoAktif = Toko::factory()->create(['status' => StatusToko::Aktif]);
    $tokoNonaktif = Toko::factory()->create(['status' => StatusToko::Ditangguhkan]);

    $tampil = Produk::factory()->for($tokoAktif)->create(['status' => StatusProduk::Aktif]);
    $produkNonaktif = Produk::factory()->for($tokoAktif)->create(['status' => StatusProduk::Draf]);
    $tokoTutup = Produk::factory()->for($tokoNonaktif)->create(['status' => StatusProduk::Aktif]);

    $penjaga = new PenjagaStok;
    $hasil = $penjaga->ambil([$tampil->id, $produkNonaktif->id, $tokoTutup->id]);

    expect($hasil[$tampil->id]->sedangTampil())->toBeTrue()
        ->and($hasil[$produkNonaktif->id]->sedangTampil())->toBeFalse()
        ->and($hasil[$tokoTutup->id]->sedangTampil())->toBeFalse();
});
