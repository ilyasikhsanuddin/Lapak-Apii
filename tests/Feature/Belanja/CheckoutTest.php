<?php

declare(strict_types=1);

use App\Models\Alamat;
use App\Models\ButirKeranjang;
use App\Models\ButirPesanan;
use App\Models\Checkout;
use App\Models\Keranjang;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;

beforeEach(function () {
    $this->andi = Pengguna::factory()->pembeli()->create();
    $this->penjual = Pengguna::factory()->penjual()->create();

    $this->toko = Toko::factory()->create([
        'pemilik_id' => $this->penjual->id,
        'nama' => 'Batik Bu Sri',
        'ongkir_rupiah' => 12_000,
    ]);

    $this->kain = Produk::factory()->for($this->toko)->create([
        'nama' => 'Kain Batik Cap Sogan',
        'harga_rupiah' => 185_000,
        'stok' => 5,
    ]);

    $this->alamat = Alamat::factory()->create([
        'pengguna_id' => $this->andi->id,
    ]);

    $this->keranjang = Keranjang::query()->forceCreate([
        'pengguna_id' => $this->andi->id,
    ]);

    $this->isiKeranjang = function (Produk $produk, int $kuantitas = 1): void {
        $this->keranjang->butir()->create([
            'produk_id' => $produk->id,
            'kuantitas' => $kuantitas,
        ]);
    };

    $this->checkout = function (
        string $kunci = 'checkout-uji-000001',
        array $tambahan = [],
    ) {
        return $this->postJson(
            '/api/v1/checkout',
            [
                'alamat_id' => $this->alamat->id,
                ...$tambahan,
            ],
            ['Idempotency-Key' => $kunci],
        );
    };
});

it('menolak checkout tanpa sesi', function () {
    ($this->checkout)()->assertUnauthorized();
});

it('menolak pengguna yang tidak memiliki izin membuat pesanan', function () {
    $this->sebagai($this->penjual);

    ($this->checkout)()->assertUnprocessable();

    expect(Checkout::count())->toBe(0);
});

it('membuat satu pesanan per toko dan satu pembayaran untuk seluruh checkout', function () {
    $penjualLain = Pengguna::factory()->penjual()->create();

    $tokoLain = Toko::factory()->create([
        'pemilik_id' => $penjualLain->id,
        'nama' => 'Batik Solo',
        'ongkir_rupiah' => 10_000,
    ]);

    $produkLain = Produk::factory()->for($tokoLain)->create([
        'nama' => 'Batik Parang',
        'harga_rupiah' => 100_000,
        'stok' => 4,
    ]);

    ($this->isiKeranjang)($this->kain, 2);
    ($this->isiKeranjang)($produkLain, 1);

    $this->sebagai($this->andi);

    ($this->checkout)()->assertCreated();

    expect(Checkout::count())->toBe(1)
        ->and(Pesanan::count())->toBe(2)
        ->and(Pembayaran::count())->toBe(1)
        ->and(ButirPesanan::count())->toBe(2)
        ->and($this->kain->refresh()->stok)->toBe(3)
        ->and($produkLain->refresh()->stok)->toBe(3)
        ->and(ButirKeranjang::count())->toBe(0);
});

it('menggunakan harga dan kuantitas dari keranjang, bukan kiriman klien', function () {
    ($this->isiKeranjang)($this->kain, 1);

    $this->sebagai($this->andi);

    ($this->checkout)(
        'checkout-uji-harga-0001',
        [
            'harga_rupiah' => 1,
            'kuantitas' => 99,
            'total_rupiah' => 1,
        ],
    )->assertCreated();

    $pesanan = Pesanan::sole();
    $butir = ButirPesanan::sole();

    expect(Checkout::sole()->total_rupiah)->toBe(197_000)
        ->and($pesanan->subtotal_rupiah)->toBe(185_000)
        ->and($butir->harga_rupiah)->toBe(185_000)
        ->and($butir->kuantitas)->toBe(1)
        ->and($this->kain->refresh()->stok)->toBe(4);
});

it('membatalkan seluruh perubahan jika salah satu produk tidak dapat dibeli', function () {
    $habis = Produk::factory()->for($this->toko)->habis()->create([
        'stok' => 0,
    ]);

    ($this->isiKeranjang)($this->kain, 1);
    ($this->isiKeranjang)($habis, 1);

    $this->sebagai($this->andi);

    ($this->checkout)()->assertStatus(409);

    expect(Checkout::count())->toBe(0)
        ->and(Pesanan::count())->toBe(0)
        ->and(Pembayaran::count())->toBe(0)
        ->and(ButirKeranjang::count())->toBe(2)
        ->and($this->kain->refresh()->stok)->toBe(5)
        ->and($habis->refresh()->stok)->toBe(0);
});

it('mengembalikan respons yang sama saat kunci idempotensi digunakan kembali', function () {
    ($this->isiKeranjang)($this->kain, 1);

    $this->sebagai($this->andi);

    ($this->checkout)('checkout-uji-ulangi-0001')->assertCreated();

    ($this->checkout)('checkout-uji-ulangi-0001')
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'true');

    expect(Checkout::count())->toBe(1)
        ->and(Pesanan::count())->toBe(1)
        ->and(Pembayaran::count())->toBe(1)
        ->and($this->kain->refresh()->stok)->toBe(4);
});

it('menolak penggunaan ulang kunci untuk permintaan yang berbeda', function () {
    ($this->isiKeranjang)($this->kain, 1);

    $alamatLain = Alamat::factory()->create([
        'pengguna_id' => $this->andi->id,
    ]);

    $this->sebagai($this->andi);

    ($this->checkout)('checkout-uji-beda-0001')->assertCreated();

    $this->postJson(
        '/api/v1/checkout',
        ['alamat_id' => $alamatLain->id],
        ['Idempotency-Key' => 'checkout-uji-beda-0001'],
    )
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'LP-11');

    expect(Checkout::count())->toBe(1)
        ->and(Pesanan::count())->toBe(1);
});

it('menolak checkout ketika keranjang kosong', function () {
    $this->sebagai($this->andi);

    ($this->checkout)()
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'KERANJANG_KOSONG');

    expect(Checkout::count())->toBe(0);
});

it('menolak alamat milik pembeli lain', function () {
    ($this->isiKeranjang)($this->kain, 1);

    $pembeliLain = Pengguna::factory()->pembeli()->create();

    $alamatOrangLain = Alamat::factory()->create([
        'pengguna_id' => $pembeliLain->id,
    ]);

    $this->sebagai($this->andi);

    $this->postJson(
        '/api/v1/checkout',
        ['alamat_id' => $alamatOrangLain->id],
        ['Idempotency-Key' => 'checkout-uji-alamat-01'],
    )->assertUnprocessable();

    expect(Checkout::count())->toBe(0)
        ->and(ButirKeranjang::count())->toBe(1)
        ->and($this->kain->refresh()->stok)->toBe(5);
});

it('menuntut sesi segar untuk checkout bernilai besar (LP-15)', function () {
    config(['lapak.transaksi.batas_segar_rupiah' => 400_000]);

    $mahal = Produk::factory()->for($this->toko)->create([
        'harga_rupiah' => 500_000,
        'stok' => 2,
    ]);

    ($this->isiKeranjang)($mahal, 1);

    $this->sebagai($this->andi, segar: false);

    ($this->checkout)()
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');

    expect(Checkout::count())->toBe(0)
        ->and(Pesanan::count())->toBe(0)
        ->and($mahal->refresh()->stok)->toBe(2)
        ->and(ButirKeranjang::count())->toBe(1);
});

it('menolak alamat yang tidak terdaftar', function () {
    ($this->isiKeranjang)($this->kain, 1);

    $this->sebagai($this->andi);

    $this->postJson(
        '/api/v1/checkout',
        ['alamat_id' => 999999],
        ['Idempotency-Key' => 'checkout-uji-alamat-02'],
    )->assertUnprocessable();

    expect(Checkout::count())->toBe(0)
        ->and(Pesanan::count())->toBe(0)
        ->and(ButirKeranjang::count())->toBe(1)
        ->and($this->kain->refresh()->stok)->toBe(5);
});
