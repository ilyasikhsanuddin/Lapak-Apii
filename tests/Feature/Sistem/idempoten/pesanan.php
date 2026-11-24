<?php

/*
| Rute POST domain D3 Keranjang dan Pesanan (LP-11).
| 'berkunci': wajib Idempotency-Key. 'tanpa_kunci': aman diulang, dengan sebab tertulis.
*/
return [
    'berkunci' => [
        'POST api/v1/checkout',
    ],

    'tanpa_kunci' => [
        'POST api/v1/simulasi-belanja' => 'Hanya menghitung, tidak menulis.',
        'POST api/v1/alamat' => 'Tidak menyentuh uang maupun stok; jumlah alamat dibatasi.',
    ],
];
