<?php

declare(strict_types=1);

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Exceptions\GalatDomain;
use App\Exceptions\TransisiPesananTidakSah;
use App\Services\LayananPesanan;
use Tests\Pendukung\PesananUji;

beforeEach(function () {
    $this->layanan = app(LayananPesanan::class);
});

it('mengemas pesanan yang sudah dibayar dan mencatat waktunya', function () {
    $data = PesananUji::dibayar();

    $hasil = $this->layanan->kemas($data['pesanan']);

    expect($hasil->status)->toBe(StatusPesanan::Dikemas)
        ->and($data['pesanan']->refresh()->dikemas_pada)->not->toBeNull();
});

it('mengirim pesanan yang sudah dikemas dan menyimpan nomor resi', function () {
    $data = PesananUji::dibayar();

    $this->layanan->kemas($data['pesanan']);

    $hasil = $this->layanan->kirim($data['pesanan'], 'JNE123456789');

    expect($hasil->status)->toBe(StatusPesanan::Dikirim)
        ->and($data['pesanan']->refresh()->nomor_resi)->toBe('JNE123456789')
        ->and($data['pesanan']->dikirim_pada)->not->toBeNull();
});

it('menolak penyelesaian pesanan yang belum dikirim', function () {
    $data = PesananUji::dibayar();

    expect(fn () => $this->layanan->terima(
        $data['pesanan'],
        PelakuPesanan::Pembeli,
    ))->toThrow(TransisiPesananTidakSah::class);

    expect($data['pesanan']->refresh()->status)->toBe(StatusPesanan::Dibayar);
});

it('menolak pembatalan pesanan yang belum dibayar', function () {
    $data = PesananUji::belumDibayar();

    expect(fn () => $this->layanan->batalkan(
        $data['pesanan'],
        PelakuPesanan::Pembeli,
        null,
        'Saya ingin membatalkan pesanan ini',
    ))->toThrow(GalatDomain::class);

    expect($data['pesanan']->refresh()->status)
        ->toBe(StatusPesanan::MenungguPembayaran);
});
