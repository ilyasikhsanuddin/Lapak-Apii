# Keputusan Sesi 2: Autentikasi dan Otorisasi

## Kesamaan dengan Laravel Sanctum

Mekanisme sesi Lapak dan Laravel Sanctum sama-sama digunakan untuk melindungi API menggunakan kredensial yang disertakan pada permintaan. Keduanya memungkinkan server mengenali pengguna, membatasi akses berdasarkan autentikasi, serta mencabut akses ketika sesi atau token tidak lagi berlaku.

## Fitur Sanctum yang Belum Dimiliki Lapak

Laravel Sanctum menyediakan autentikasi SPA menggunakan cookie dan perlindungan CSRF, serta personal access token dengan abilities untuk membatasi tindakan yang boleh dilakukan. Sanctum juga menyediakan pengelolaan dan pencabutan token.

Lapak menggunakan kunci sesi sendiri berbasis ULID dan random bytes, dengan pencatatan sesi di basis data, pemeriksaan sesi, sesi segar, dan pembatasan laju. Lapak belum memiliki seluruh integrasi SPA dan kemampuan token seperti Sanctum.

## Kapan Memilih JWT atau OAuth 2.0?

**JWT** dipertimbangkan apabila sistem membutuhkan token mandiri berisi klaim terverifikasi yang dapat diperiksa oleh beberapa layanan tanpa selalu membaca sesi dari basis data. Penggunaan JWT perlu mempertimbangkan masa berlaku, rotasi kunci, dan pencabutan token.

**OAuth 2.0** dipertimbangkan apabila Lapak perlu memberikan akses kepada aplikasi pihak ketiga atas nama pengguna, misalnya untuk integrasi mitra. OAuth 2.0 menyediakan alur otorisasi dan pembatasan akses menggunakan scope. Token OAuth dapat berbentuk JWT maupun opaque token.

## Keputusan Tim

Untuk kebutuhan Lapak saat ini, mekanisme sesi berbasis kunci tetap digunakan karena kebutuhan utama adalah autentikasi pengguna dan pengelolaan sesi perangkat. JWT atau OAuth 2.0 baru dipertimbangkan ketika kebutuhan integrasi layanan dan pihak ketiga muncul.
