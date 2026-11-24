<?php

/*
| Rute POST domain D4 Pembayaran dan Saldo (LP-11).
| 'berkunci': wajib Idempotency-Key. 'tanpa_kunci': aman diulang, dengan sebab tertulis.
*/
return [
    'berkunci' => [],

    'tanpa_kunci' => [
        'POST api/v1/webhook/pembayaran' => 'Dijaga status tagihan di bawah gembok checkout.',
    ],
];
