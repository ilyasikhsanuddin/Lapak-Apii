<?php

declare(strict_types=1);

use App\Models\Kategori;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\PenjagaStok;

it('menambah dan mengurangi stok penjual secara relatif dengan kunci idempotensi', function (): void {
    $penjual = Pengguna::factory()->penjual()->create();
    $toko = Toko::factory()->for($penjual, 'pemilik')->create();
    $produk = Produk::factory()->for($toko)->create(['stok' => 10]);

    // Tambah stok (+5 -> 15)
    $this->sebagai($penjual)
        ->withHeader('Idempotency-Key', 'kunci-penyesuaian-001')
        ->postJson("/api/v1/toko-saya/produk/{$produk->id}/stok", ['selisih' => 5])
        ->assertOk()
        ->assertJsonPath('data.stok', 15);

    expect($produk->fresh()->stok)->toBe(15);

    // Ulangi dengan Idempotency-Key yang sama: stok tidak bertambah dua kali (LP-11)
    $this->sebagai($penjual)
        ->withHeader('Idempotency-Key', 'kunci-penyesuaian-001')
        ->postJson("/api/v1/toko-saya/produk/{$produk->id}/stok", ['selisih' => 5])
        ->assertOk()
        ->assertHeader('Idempotency-Replayed', 'true')
        ->assertJsonPath('data.stok', 15);

    expect($produk->fresh()->stok)->toBe(15);

    // Kurangi stok (-6 -> 9)
    $this->sebagai($penjual)
        ->withHeader('Idempotency-Key', 'kunci-penyesuaian-002')
        ->postJson("/api/v1/toko-saya/produk/{$produk->id}/stok", ['selisih' => -6])
        ->assertOk()
        ->assertJsonPath('data.stok', 9);

    expect($produk->fresh()->stok)->toBe(9);
});

it('menolak pengurangan stok yang menyebabkan negatif atau melebihi batas dengan 409 LP-4', function (): void {
    $penjual = Pengguna::factory()->penjual()->create();
    $toko = Toko::factory()->for($penjual, 'pemilik')->create();
    $produk = Produk::factory()->for($toko)->create(['stok' => 4]);

    // Mengurangi 5 dari stok 4 -> ditolak 409 LP-4
    $this->sebagai($penjual)
        ->withHeader('Idempotency-Key', 'kunci-penyesuaian-negatif')
        ->postJson("/api/v1/toko-saya/produk/{$produk->id}/stok", ['selisih' => -5])
        ->assertConflict()
        ->assertJsonPath('galat.kode', 'LP-4');

    expect($produk->fresh()->stok)->toBe(4);

    // Menambah melewati MAKSIMUM -> ditolak 409 LP-4
    $this->sebagai($penjual)
        ->withHeader('Idempotency-Key', 'kunci-penyesuaian-batas')
        ->postJson("/api/v1/toko-saya/produk/{$produk->id}/stok", ['selisih' => PenjagaStok::MAKSIMUM])
        ->assertConflict()
        ->assertJsonPath('galat.kode', 'LP-4');

    expect($produk->fresh()->stok)->toBe(4);
});

it('menolak perubahan stok lewat PATCH produk dan menolak akses milik toko lain', function (): void {
    $penjual = Pengguna::factory()->penjual()->create();
    $toko = Toko::factory()->for($penjual, 'pemilik')->create();
    $produk = Produk::factory()->for($toko)->for(Kategori::factory())->create(['stok' => 10]);

    // PATCH tidak boleh menerima kolom stok
    $this->sebagai($penjual)
        ->patchJson("/api/v1/toko-saya/produk/{$produk->id}", ['stok' => 99])
        ->assertUnprocessable()
        ->assertJsonStructure(['galat' => ['rincian' => ['stok']]]);

    // Penjual lain mendapat 404 (LP-5)
    $penjualLain = Pengguna::factory()->penjual()->create();
    Toko::factory()->for($penjualLain, 'pemilik')->create();

    $this->sebagai($penjualLain)
        ->withHeader('Idempotency-Key', 'kunci-penyesuaian-asing')
        ->postJson("/api/v1/toko-saya/produk/{$produk->id}/stok", ['selisih' => 5])
        ->assertNotFound();

    // Selisih 0 ditolak validasi
    $this->sebagai($penjual)
        ->withHeader('Idempotency-Key', 'kunci-penyesuaian-nol')
        ->postJson("/api/v1/toko-saya/produk/{$produk->id}/stok", ['selisih' => 0])
        ->assertUnprocessable()
        ->assertJsonStructure(['galat' => ['rincian' => ['selisih']]]);
});
