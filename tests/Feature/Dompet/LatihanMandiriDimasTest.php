<?php

use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Models\MutasiSaldo;
use App\Services\BukuBesar;

it('menampilkan riwayat mutasi dompet beserta saldo berjalan (Latihan Mandiri 6)', function () {
    $buku = app(BukuBesar::class);
    $eksternal = $buku->dompetSistem(JenisDompet::Eksternal);
    $escrow = $buku->dompetSistem(JenisDompet::Escrow);

    $buku->pindahkan($eksternal, $escrow, Uang::dari(100_000), JenisMutasi::DanaMasuk, 'LPK-MANDIRI-1');
    $buku->pindahkan($eksternal, $escrow, Uang::dari(50_000), JenisMutasi::DanaMasuk, 'LPK-MANDIRI-2');

    $this->artisan('lapak:mutasi', ['kode' => 'escrow'])
        ->expectsOutputToContain('Riwayat Mutasi Dompet: escrow')
        ->expectsOutputToContain('Verifikasi saldo berjalan cocok dengan saldo_rupiah: Rp 150.000')
        ->assertSuccessful();
});

it('membuktikan kueri langsung lolos dari penjaga model booted (Latihan Mandiri 7)', function () {
    $buku = app(BukuBesar::class);
    $eksternal = $buku->dompetSistem(JenisDompet::Eksternal);
    $escrow = $buku->dompetSistem(JenisDompet::Escrow);

    $buku->pindahkan($eksternal, $escrow, Uang::dari(10_000), JenisMutasi::DanaMasuk, 'LPK-MANDIRI-3');

    // MutasiSaldo booted() hanya menangkap event model individual, kueri massal lolos
    $affected = MutasiSaldo::query()->update(['jumlah_rupiah' => 99_999]);

    expect($affected)->toBeGreaterThan(0);
});
