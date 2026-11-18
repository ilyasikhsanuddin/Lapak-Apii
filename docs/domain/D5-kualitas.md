# Piagam Domain D5 — Otorisasi, Audit, dan Kualitas

## Deskripsi
Menjawab tiga pertanyaan: siapa boleh melakukan apa, siapa yang sudah melakukan apa, dan apakah `main` layak dipercaya. D5 memegang tabel `peran`, `izin`, `kunci_idempotensi`, dan `log_audit` (menyusul pada sesi berikutnya), serta kontrak lintas domain: amplop respons, penanganan galat terpusat, dan gerbang kualitas (CI, gaya kode, uji).

## Yang dibutuhkan dari domain lain
- **Domain D1 (Pengguna & Identitas):** tabel dan model `Pengguna` sebagai penerima peran dan sebagai pelaku yang dicatat pada `log_audit`.
- **Domain D2, D3, D4:** setiap domain melempar `GalatDomain` dengan kode aturan miliknya (misalnya LP-4, LP-13) dan membungkus respons dengan `ResponsApi`, supaya tabel kode galat di `docs/KONTRAK-API.md` tetap lengkap.
- **Domain D4 (Pembayaran & Saldo):** peristiwa pemindahan saldo untuk dicatat pada `log_audit` (rencana sesi berikutnya).
- **Semua domain:** rute ditulis di `routes/api/<domain>.php`, dan `DatabaseSeeder` hanya ditambah satu baris per seeder, mengikuti arah foreign key.

## Yang disediakan untuk domain lain
- **`ResponsApi` dan `GalatDomain`:** amplop sukses dan galat berbentuk kontrak, dipakai semua domain.
- **Penanganan galat terpusat:** respons 404, 405, dan 422 berbentuk JSON kontrak, serta pesan validasi berbahasa Indonesia (`lang/id/validation.php`).
- **Middleware `IdPermintaan` dan `PaksaJson`:** setiap respons membawa header `X-Request-Id`, dan setiap permintaan dijawab sebagai JSON.
- **`GET /api/v1/health`:** pemeriksaan bahwa middleware D5 aktif.
- **Gerbang kualitas:** CI dengan Pint dan Pest, uji arsitektur, uji asap seeder, dan uji kontrak galat.
- **Pemilik berkas bersama:** `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`, `config/lapak.php`, `composer.json`, `.github/`, dan `database/seeders/DatabaseSeeder.php`.
- **Rencana:** tabel `peran` dan `izin` untuk otorisasi, `kunci_idempotensi` untuk permintaan ulang yang aman, dan `log_audit` dengan rantai hash.