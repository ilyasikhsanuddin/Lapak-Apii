<?php

declare(strict_types=1);

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Enums\PeristiwaAudit;
use App\Models\LogAudit;
use App\Models\MutasiSaldo;
use App\Services\BukuBesar;
use App\Services\LayananPesanan;
use App\Services\PenyelesaianOtomatis;
use Tests\Pendukung\PesananUji;

beforeEach(function () {
    $this->buku = app(BukuBesar::class);
    $this->layanan = app(LayananPesanan::class);
    $this->otomatis = app(PenyelesaianOtomatis::class);

    $this->siapkanPesananTerkirim = function (int $umurHari = 4): array {
        $data = PesananUji::dibayar();
        $pesanan = $data['pesanan'];

        $this->layanan->kemas($pesanan);
        $this->layanan->kirim($pesanan, 'JNE-UJI-123456');

        $pesanan->forceFill([
            'dikirim_pada' => now()->subDays($umurHari),
        ])->save();

        $eksternal = $this->buku->dompetSistem(JenisDompet::Eksternal);
        $escrow = $this->buku->dompetSistem(JenisDompet::Escrow);

        $this->buku->pindahkan(
            $eksternal,
            $escrow,
            Uang::dari($pesanan->total_rupiah),
            JenisMutasi::DanaMasuk,
            $pesanan->nomor,
            'Pembayaran uji',
        );

        $data['escrow'] = $escrow;
        $data['pendapatan'] = $this->buku->dompetSistem(JenisDompet::Pendapatan);
        $data['penjual'] = $this->buku->dompetPenjual($pesanan->toko->pemilik);

        return $data;
    };
});

it('menyelesaikan pesanan yang sudah melewati masa penahanan dana', function () {
    $data = ($this->siapkanPesananTerkirim)(4);

    expect($this->otomatis->jalankan())->toBe(1)
        ->and($data['pesanan']->refresh()->status)->toBe(StatusPesanan::Selesai)
        ->and($data['escrow']->refresh()->saldo_rupiah)->toBe(0)
        ->and($data['pendapatan']->refresh()->saldo_rupiah)->toBe(18_500)
        ->and($data['penjual']->refresh()->saldo_rupiah)->toBe(363_500)
        ->and(LogAudit::query()->where('peristiwa', PeristiwaAudit::DanaDilepas->value)->count())->toBe(1);
});

it('melewati pesanan yang belum melewati masa penahanan dana', function () {
    $data = ($this->siapkanPesananTerkirim)(1);

    expect($this->otomatis->jalankan())->toBe(0)
        ->and($data['pesanan']->refresh()->status)->toBe(StatusPesanan::Dikirim)
        ->and($data['escrow']->refresh()->saldo_rupiah)->toBe(382_000)
        ->and($data['pendapatan']->refresh()->saldo_rupiah)->toBe(0)
        ->and($data['penjual']->refresh()->saldo_rupiah)->toBe(0)
        ->and(LogAudit::query()->where('peristiwa', PeristiwaAudit::DanaDilepas->value)->count())->toBe(0);
});

it('tidak melepas dana atau mencatat audit dua kali ketika dijalankan berulang', function () {
    $data = ($this->siapkanPesananTerkirim)(4);

    expect($this->otomatis->jalankan())->toBe(1)
        ->and($this->otomatis->jalankan())->toBe(0)
        ->and($data['pesanan']->refresh()->status)->toBe(StatusPesanan::Selesai)
        ->and($data['escrow']->refresh()->saldo_rupiah)->toBe(0)
        ->and($data['pendapatan']->refresh()->saldo_rupiah)->toBe(18_500)
        ->and($data['penjual']->refresh()->saldo_rupiah)->toBe(363_500)
        ->and(MutasiSaldo::query()->where('rujukan', $data['pesanan']->nomor)->count())->toBe(6)
        ->and(LogAudit::query()->where('peristiwa', PeristiwaAudit::DanaDilepas->value)->count())->toBe(1);
});

it('melewati kandidat lama jika pembeli lebih dahulu menyelesaikan pesanan', function () {
    $data = ($this->siapkanPesananTerkirim)(4);

    $this->layanan->terima($data['pesanan'], PelakuPesanan::Pembeli);

    expect($this->otomatis->selesaikan($data['pesanan']))->toBeFalse()
        ->and($data['pesanan']->refresh()->status)->toBe(StatusPesanan::Selesai)
        ->and($data['escrow']->refresh()->saldo_rupiah)->toBe(0)
        ->and($data['pendapatan']->refresh()->saldo_rupiah)->toBe(18_500)
        ->and($data['penjual']->refresh()->saldo_rupiah)->toBe(363_500)
        ->and(MutasiSaldo::query()->where('rujukan', $data['pesanan']->nomor)->count())->toBe(6)
        ->and(LogAudit::query()->where('peristiwa', PeristiwaAudit::DanaDilepas->value)->count())->toBe(1);
});
