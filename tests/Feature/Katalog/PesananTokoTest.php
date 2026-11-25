<?php

declare(strict_types=1);

use App\Domain\Pesanan\StatusPesanan;
use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Models\ButirPesanan;
use App\Models\Checkout;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\BukuBesar;

beforeEach(function (): void {
    $this->andi = Pengguna::factory()->pembeli()->create();
    $this->sri = Pengguna::factory()->penjual()->create();
    $this->tokoSri = Toko::factory()->for($this->sri, 'pemilik')->create();
    $this->kain = Produk::factory()->for($this->tokoSri)->create(['stok' => 3]);

    $checkout = Checkout::factory()->for($this->andi, 'pembeli')->create();
    $this->pesanan = Pesanan::factory()
        ->for($checkout)
        ->for($this->andi, 'pembeli')
        ->for($this->tokoSri, 'toko')
        ->create([
            'status' => StatusPesanan::Dibayar,
            'dibayar_pada' => now(),
            'subtotal_rupiah' => 370_000,
            'ongkir_rupiah' => 12_000,
            'total_rupiah' => 382_000,
        ]);

    ButirPesanan::factory()->for($this->pesanan)->create([
        'produk_id' => $this->kain->id,
        'harga_rupiah' => 185_000,
        'kuantitas' => 2,
        'subtotal_rupiah' => 370_000,
    ]);

    $buku = app(BukuBesar::class);
    $buku->pindahkan(
        $buku->dompetSistem(JenisDompet::Eksternal),
        $buku->dompetSistem(JenisDompet::Escrow),
        Uang::dari(382_000),
        JenisMutasi::DanaMasuk,
        $this->pesanan->nomor,
    );

    $this->alamat = fn (string $akhiran = '') => "/api/v1/toko-saya/pesanan/{$this->pesanan->nomor}{$akhiran}";
    $this->batal = fn (string $kunci = 'batal-pesanan-0001') => $this
        ->withHeader('Idempotency-Key', $kunci)
        ->postJson(($this->alamat)('/batal'), ['alasan' => 'Stok motif tersebut rusak']);
});

it('menahan alamat kirim sampai pesanan dibayar', function (): void {
    $checkout = Checkout::factory()->for($this->andi, 'pembeli')->create();
    $belum = Pesanan::factory()
        ->for($checkout)
        ->for($this->andi, 'pembeli')
        ->for($this->tokoSri, 'toko')
        ->create(['status' => StatusPesanan::MenungguPembayaran]);

    $this->sebagai($this->sri)
        ->getJson("/api/v1/toko-saya/pesanan/{$belum->nomor}")
        ->assertOk()
        ->assertJsonPath('data.status', 'menunggu_pembayaran')
        ->assertJsonPath('data.alamat_kirim', null)
        ->assertJsonPath('data.tindakan', []);
});

it('menjawab 404 untuk pesanan toko lain pada setiap pintu', function (): void {
    $wulan = Pengguna::factory()->penjual()->create();
    Toko::factory()->for($wulan, 'pemilik')->create();

    $this->sebagai($wulan);
    $this->getJson(($this->alamat)())->assertNotFound();
    $this->postJson(($this->alamat)('/kemas'))->assertNotFound();
    $this->postJson(($this->alamat)('/kirim'), ['nomor_resi' => 'JNE-00012345'])->assertNotFound();
    ($this->batal)()->assertNotFound();

    expect($this->pesanan->refresh()->status)->toBe(StatusPesanan::Dibayar);
});

it('mengemas lalu mengirim, dan menolak melompati urutan (LP-10)', function (): void {
    $this->sebagai($this->sri);

    // Belum dikemas: tidak dapat langsung dikirim.
    $this->postJson(($this->alamat)('/kirim'), ['nomor_resi' => 'JNE-00012345'])
        ->assertConflict()
        ->assertJsonPath('galat.kode', 'LP-10');

    $this->postJson(($this->alamat)('/kemas'))
        ->assertOk()
        ->assertJsonPath('data.status', 'dikemas')
        ->assertJsonPath('data.tindakan', ['dikirim', 'dibatalkan']);

    // Mengulang kemas ditolak 409.
    $this->postJson(($this->alamat)('/kemas'))->assertConflict();

    $this->postJson(($this->alamat)('/kirim'), ['nomor_resi' => ' jne-00012345 '])
        ->assertOk()
        ->assertJsonPath('data.status', 'dikirim')
        ->assertJsonPath('data.nomor_resi', 'JNE-00012345')
        ->assertJsonPath('data.tindakan', []);

    // Mengemas dan mengirim tidak memindahkan uang: dana tetap ditahan di escrow.
    $escrow = app(BukuBesar::class)->dompetSistem(JenisDompet::Escrow)->saldo_rupiah;
    expect($escrow)->toBe(382_000);
});

it('membatalkan pesanan oleh penjual: stok kembali, dana kembali, dan idempoten (LP-10, LP-11)', function (): void {
    $this->sebagai($this->sri);

    ($this->batal)('batal-toko-kunci-01')
        ->assertOk()
        ->assertJsonPath('data.status', 'dibatalkan')
        ->assertJsonPath('data.dibatalkan_oleh', 'penjual');

    // Pengulangan dengan kunci idempotensi yang sama memutar ulang respons tanpa efek ganda.
    ($this->batal)('batal-toko-kunci-01')
        ->assertOk()
        ->assertHeader('Idempotency-Replayed', 'true');

    expect($this->kain->refresh()->stok)->toBe(5)
        ->and(app(BukuBesar::class)->dompetSistem(JenisDompet::Escrow)->saldo_rupiah)->toBe(0)
        ->and(LogAudit::query()->where('sasaran', "pesanan:{$this->pesanan->nomor}")->count())->toBe(2);
});
