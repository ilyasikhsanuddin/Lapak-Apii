<?php

/*
| Rute POST domain D1 Identitas dan Sesi (LP-11).
| 'berkunci': wajib Idempotency-Key. 'tanpa_kunci': aman diulang, dengan sebab tertulis.
*/
return [
    'berkunci' => [],

    'tanpa_kunci' => [
        'POST api/v1/registrasi' => 'Surel unik: pengulangan dijawab 422, tidak membuat akun kedua.',
        'POST api/v1/masuk' => 'Pengulangan hanya menerbitkan sesi tambahan yang dapat dicabut.',
        'POST api/v1/keluar' => 'Mencabut sesi yang sudah dicabut tidak mengubah apa pun.',
        'POST api/v1/sesi/konfirmasi' => 'Kunci lama mati setelah berhasil; pengulangan dijawab 401.',
        'POST api/v1/admin/pengguna/{id}/tangguhkan' => 'UPDATE bersyarat: pengulangan dijawab 409.',
        'POST api/v1/admin/pengguna/{id}/pulihkan' => 'UPDATE bersyarat: pengulangan dijawab 409.',
    ],
];
