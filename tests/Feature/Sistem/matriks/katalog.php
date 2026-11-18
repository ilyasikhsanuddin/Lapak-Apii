<?php

/*
| Matriks otorisasi domain D2 Toko dan Katalog.
| Kunci: "METODE alamat". Nilai: daftar peran yang boleh, atau '*' untuk siapa pun yang sudah masuk.
*/
return [
    'GET api/v1/toko-saya' => '*',
    'POST api/v1/toko-saya' => '*',
    'PATCH api/v1/toko-saya' => '*',
    'GET api/v1/toko-saya/produk' => '*',
    'POST api/v1/toko-saya/produk' => '*',
    'PATCH api/v1/toko-saya/produk/{produk}' => '*',
    'DELETE api/v1/toko-saya/produk/{produk}' => '*',
];
