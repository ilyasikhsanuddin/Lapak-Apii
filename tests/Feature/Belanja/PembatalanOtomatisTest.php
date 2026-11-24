<?php

declare(strict_types=1);

use App\Domain\Pesanan\StatusPesanan;
use App\Enums\StatusPembayaran;
use App\Exceptions\TransisiPesananTidakSah;
use App\Models\ButirPesanan;
use App\Models\Checkout;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Services\PembatalanOtomatis;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->layanan = app(PembatalanOtomatis::class);
});

it('membatalkan seluruh pesanan dan mengembalikan stok ketika pembayaran kedaluwarsa', function () {
    $checkout = Checkout::factory()->create();

    $pesananA = Pesanan::factory()->for($checkout)->create();
    $pesananB = Pesanan::factory()->for($checkout)->create();

    $produkA = Produk::factory()->create(['stok' => 3]);
    $produkB = Produk::factory()->create(['stok' => 5]);

    ButirPesanan::factory()->for($pesananA)->create([
        'produk_id' => $produkA->id,
        'kuantitas' => 2,
    ]);

    ButirPesanan::factory()->for($pesananB)->create([
        'produk_id' => $produkB->id,
        'kuantitas' => 4,
    ]);

    $pembayaran = Pembayaran::factory()->for($checkout)->create([
        'batas_bayar_pada' => now()->subSecond(),
    ]);

    expect($this->layanan->jalankan())->toBe(1)
        ->and($pembayaran->refresh()->status)->toBe(StatusPembayaran::Kedaluwarsa)
        ->and($pesananA->refresh()->status)->toBe(StatusPesanan::Dibatalkan)
        ->and($pesananB->refresh()->status)->toBe(StatusPesanan::Dibatalkan)
        ->and($produkA->refresh()->stok)->toBe(5)
        ->and($produkB->refresh()->stok)->toBe(9)
        ->and($pesananA->alasan_batal)->toBe('Tidak dibayar dalam batas waktu pembayaran.')
        ->and($pesananA->dibatalkan_pada)->not->toBeNull();
});

it('tidak membatalkan checkout yang belum melewati batas pembayaran', function () {
    $checkout = Checkout::factory()->create();
    $pesanan = Pesanan::factory()->for($checkout)->create();

    $produk = Produk::factory()->create(['stok' => 3]);

    ButirPesanan::factory()->for($pesanan)->create([
        'produk_id' => $produk->id,
        'kuantitas' => 2,
    ]);

    $pembayaran = Pembayaran::factory()->for($checkout)->create([
        'batas_bayar_pada' => now()->addHour(),
    ]);

    expect($this->layanan->jalankan())->toBe(0)
        ->and($pembayaran->refresh()->status)->toBe(StatusPembayaran::Menunggu)
        ->and($pesanan->refresh()->status)->toBe(StatusPesanan::MenungguPembayaran)
        ->and($produk->refresh()->stok)->toBe(3);
});

it('tidak membatalkan checkout yang pembayarannya sudah lunas', function () {
    $checkout = Checkout::factory()->create();
    $pesanan = Pesanan::factory()->for($checkout)->create();

    $produk = Produk::factory()->create(['stok' => 3]);

    ButirPesanan::factory()->for($pesanan)->create([
        'produk_id' => $produk->id,
        'kuantitas' => 2,
    ]);

    $pembayaran = Pembayaran::factory()->for($checkout)->create([
        'status' => StatusPembayaran::Lunas,
        'batas_bayar_pada' => now()->subHour(),
    ]);

    expect($this->layanan->jalankan())->toBe(0)
        ->and($pembayaran->refresh()->status)->toBe(StatusPembayaran::Lunas)
        ->and($pesanan->refresh()->status)->toBe(StatusPesanan::MenungguPembayaran)
        ->and($produk->refresh()->stok)->toBe(3);
});

it('tidak mengembalikan stok dua kali ketika pembatalan dijalankan berulang', function () {
    $checkout = Checkout::factory()->create();
    $pesanan = Pesanan::factory()->for($checkout)->create();

    $produk = Produk::factory()->create(['stok' => 3]);

    ButirPesanan::factory()->for($pesanan)->create([
        'produk_id' => $produk->id,
        'kuantitas' => 2,
    ]);

    Pembayaran::factory()->for($checkout)->create([
        'batas_bayar_pada' => now()->subSecond(),
    ]);

    expect($this->layanan->jalankan())->toBe(1)
        ->and($this->layanan->jalankan())->toBe(0)
        ->and($produk->refresh()->stok)->toBe(5);
});

it('membatalkan seluruh perubahan jika salah satu pesanan tidak dapat dibatalkan', function () {
    $checkout = Checkout::factory()->create();

    $pesananA = Pesanan::factory()->for($checkout)->create();
    $pesananB = Pesanan::factory()->for($checkout)->create([
        'status' => StatusPesanan::Selesai,
    ]);

    $produk = Produk::factory()->create(['stok' => 3]);

    ButirPesanan::factory()->for($pesananA)->create([
        'produk_id' => $produk->id,
        'kuantitas' => 2,
    ]);

    Pembayaran::factory()->for($checkout)->create([
        'batas_bayar_pada' => now()->subSecond(),
    ]);

    expect(fn () => $this->layanan->jalankan())
        ->toThrow(TransisiPesananTidakSah::class);

    expect($pesananA->refresh()->status)->toBe(StatusPesanan::MenungguPembayaran)
        ->and($pesananB->refresh()->status)->toBe(StatusPesanan::Selesai)
        ->and($produk->refresh()->stok)->toBe(3)
        ->and(Pembayaran::query()->where('checkout_id', $checkout->id)->firstOrFail()->status)
        ->toBe(StatusPembayaran::Menunggu);
});

it('mengembalikan stok urut nomor produk, apa pun urutan butirnya', function () {
    $checkout = Checkout::factory()->create();
    $pesanan = Pesanan::factory()->for($checkout)->create();

    $kecil = Produk::factory()->create(['stok' => 0]);
    $tengah = Produk::factory()->create(['stok' => 0]);
    $besar = Produk::factory()->create(['stok' => 0]);

    foreach ([$besar, $kecil, $tengah] as $produk) {
        ButirPesanan::factory()->for($pesanan)->create([
            'produk_id' => $produk->id,
            'kuantitas' => 1,
        ]);
    }

    Pembayaran::factory()->for($checkout)->create([
        'batas_bayar_pada' => now()->subSecond(),
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    expect($this->layanan->jalankan())->toBe(1);

    $idProdukDiperbarui = collect(DB::getQueryLog())
        ->filter(fn (array $query) => preg_match(
            '/^update\s+["`]?produk["`]?/i',
            ltrim($query['query'])
        ))
        ->map(fn (array $query) => (int) $query['bindings'][array_key_last($query['bindings'])])
        ->values()
        ->all();

    expect($idProdukDiperbarui)->toBe([
        $kecil->id,
        $tengah->id,
        $besar->id,
    ]);
});
