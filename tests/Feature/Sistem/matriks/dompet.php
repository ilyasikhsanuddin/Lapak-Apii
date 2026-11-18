<?php

/*
| Matriks otorisasi domain D4 Pembayaran dan Saldo.
| Kunci: "METODE alamat". Nilai: daftar peran yang boleh, atau '*' untuk siapa pun yang sudah masuk.
*/
return [
    'GET api/v1/dompet' => '*',
    'GET api/v1/dompet/mutasi' => '*',
];
