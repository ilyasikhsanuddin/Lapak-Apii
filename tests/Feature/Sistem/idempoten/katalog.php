<?php

/*
| Rute POST domain D2 Toko dan Katalog (LP-11).
| 'berkunci': wajib Idempotency-Key. 'tanpa_kunci': aman diulang, dengan sebab tertulis.
*/
return [
    'berkunci' => [
        'POST api/v1/toko-saya/produk/{id}/stok',
    ],

    'tanpa_kunci' => [
        'POST api/v1/toko-saya' => 'Satu pengguna satu toko (LP-3): pengulangan dijawab 409.',
        'POST api/v1/toko-saya/produk' => 'Tidak menyentuh uang; produk kembar dapat diarsipkan.',
        'POST api/v1/admin/toko/{id}/setujui' => 'UPDATE bersyarat: pengulangan dijawab 409.',
        'POST api/v1/admin/toko/{id}/tangguhkan' => 'UPDATE bersyarat: pengulangan dijawab 409.',
        'POST api/v1/admin/toko/{id}/tolak' => 'UPDATE bersyarat: pengulangan dijawab 409.',
    ],
];
