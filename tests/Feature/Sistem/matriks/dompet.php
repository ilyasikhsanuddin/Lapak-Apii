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
];
