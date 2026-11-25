<?php

/*
| Matriks otorisasi domain D2 Toko dan Katalog.
| Kunci: "METODE alamat". Nilai: daftar peran yang boleh, atau '*' untuk siapa pun yang sudah masuk.
*/
return [
    'GET api/v1/toko-saya' => '*',
    'PATCH api/v1/toko-saya' => '*',
    'POST api/v1/toko-saya' => ['pembeli'],
    'GET api/v1/toko-saya/produk' => ['penjual'],
    'POST api/v1/toko-saya/produk' => ['penjual'],
    'PATCH api/v1/toko-saya/produk/{produk}' => ['penjual'],
    'POST api/v1/toko-saya/produk/{id}/stok' => ['penjual'],
    'DELETE api/v1/toko-saya/produk/{produk}' => ['penjual'],
    'GET api/v1/toko-saya/pesanan' => ['penjual'],
    'GET api/v1/toko-saya/pesanan/{nomor}' => ['penjual'],
    'POST api/v1/toko-saya/pesanan/{nomor}/kemas' => ['penjual'],
    'POST api/v1/toko-saya/pesanan/{nomor}/kirim' => ['penjual'],
    'POST api/v1/toko-saya/pesanan/{nomor}/batal' => ['penjual'],
    'GET api/v1/admin/toko' => ['admin'],
    'POST api/v1/admin/toko/{id}/setujui' => ['admin'],
    'POST api/v1/admin/toko/{id}/tolak' => ['admin'],
    'POST api/v1/admin/toko/{id}/tangguhkan' => ['admin'],
];
