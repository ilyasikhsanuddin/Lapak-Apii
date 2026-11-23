<?php

use App\Domain\Pesanan\KedudukanPesanan;
use App\Domain\Pesanan\PelakuPesanan;

it('mengenali pengguna sebagai pembeli pesanan', function () {
    expect(KedudukanPesanan::dari(10, 10, 20))
        ->toBe(PelakuPesanan::Pembeli);
});

it('mengenali pemilik toko sebagai penjual pesanan', function () {
    expect(KedudukanPesanan::dari(20, 10, 20))
        ->toBe(PelakuPesanan::Penjual);
});

it('menghasilkan null jika pengguna bukan pembeli maupun pemilik toko', function () {
    expect(KedudukanPesanan::dari(30, 10, 20))
        ->toBeNull();
});

it('mengutamakan kedudukan pembeli jika kedua id pihak sama', function () {
    expect(KedudukanPesanan::dari(10, 10, 10))
        ->toBe(PelakuPesanan::Pembeli);
});
