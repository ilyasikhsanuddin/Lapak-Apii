<?php

/*
| Rute POST yang wajib memakai Idempotency-Key.
*/

return [
    'berkunci' => [
        'POST api/v1/checkout',
        'POST api/v1/pesanan/{nomor}/terima',
        'POST api/v1/pesanan/{nomor}/batal',
        'POST api/v1/checkout/{kode}/batal',
    ],

    'tanpa_kunci' => [
        'POST api/v1/simulasi-belanja' => 'Hanya menghitung, tidak menulis.',
        'POST api/v1/alamat' => 'Tidak menyentuh uang maupun stok; jumlah alamat dibatasi.',
    ],
];
