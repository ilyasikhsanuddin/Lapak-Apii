<?php

/*
| Matriks otorisasi domain dompet dan buku besar.
| Kunci: "METODE alamat". Nilai: daftar peran yang boleh.
*/
return [
    'GET api/v1/dompet' => ['penjual'],
    'GET api/v1/dompet/mutasi' => ['penjual'],

    'GET api/v1/buku-besar/dompet' => ['keuangan', 'auditor'],
    'GET api/v1/buku-besar/dompet/{id}/mutasi' => ['keuangan', 'auditor'],
    'GET api/v1/buku-besar/kelompok/{kelompok}' => ['keuangan', 'auditor'],

    'GET api/v1/pembayaran/{kode}' => ['pembeli'],
    'POST api/v1/dompet/pencairan' => ['penjual'],
    'GET api/v1/dompet/pencairan' => ['penjual'],
    'GET api/v1/keuangan/pencairan' => ['keuangan'],
    'POST api/v1/keuangan/pencairan/{kode}/setujui' => ['keuangan'],
    'POST api/v1/keuangan/pencairan/{kode}/tolak' => ['keuangan'],
];
