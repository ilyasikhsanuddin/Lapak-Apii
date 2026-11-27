<?php

/*
| Matriks otorisasi domain D5 Sistem (LP-2, LP-17).
| Kunci: "METODE alamat". Nilai: daftar peran yang boleh.
*/
return [
    'GET api/v1/audit' => ['auditor'],
    'GET api/v1/audit/rantai' => ['auditor'],
];
