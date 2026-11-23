# Log Penggunaan AI - Hilma

## Sesi 1
- **Alat:** Gemini AI
- **Penggunaan:** Pemahaman alur kerja Git

## Sesi 2
- **Alat:** Gemini AI
- **Penggunaan:**
  - **Kontrak A3:** Menyusun spesifikasi pengujian `TokoSayaTest` dan registrasi rute terlindung `toko-saya` sesuai aturan kepemilikan toko (`LP-3`, `LP-4`).
  - **Implementasi B3:** Membangun `TokoSayaController`, `ProdukSayaController`, `ProdukSayaResource`, dan aturan `SudahPunyaToko` beserta pengujian CRUD produk sendiri (`ProdukSayaTest`).
  - **Latihan Mandiri Sesi 2:** Menambahkan validasi kueri `DaftarProdukSayaRequest` (`status` dan `cari`) dengan pelolosan karakter pengganti (`!`, `%`, `_`) pada `ProdukSayaController@index` serta pengujian `LatihanProdukSayaTest`.
  - **Integrator Sesi 2:** Memverifikasi integrasi cabang Sesi 2, merapikan ekspektasi seeder gabungan pada `DompetSayaTest`, dan menyiapkan PR Penutup Sesi 2.
- **Verifikasi:** Seluruh perubahan diverifikasi secara mandiri menggunakan `./vendor/bin/pint --test`, `php artisan migrate:fresh --seed`, dan `./vendor/bin/pest` (100% hijau).

## Sesi 3
- **Alat:** Gemini AI
- **Penggunaan:**
  - **Implementasi B3:** Membangun sistem moderasi toko (`ModerasiToko`, `ModerasiTokoController`, `TokoPolicy`, `TransisiTokoTidakSah`, migrasi jejak moderasi, pembaruan `KatalogSeeder`), memasang middleware izin pada rute `toko-saya` dan `admin/toko`, memperbarui `tests/Feature/Sistem/matriks/katalog.php`, serta menulis `ModerasiTokoTest` dan menyesuaikan `ProdukSayaTest`.
  - **Latihan Mandiri #4:** Menambahkan status `ditolak`, pintu `POST /api/v1/admin/toko/{id}/tolak` dengan alasan wajib, dan pengajuan ulang lewat `PATCH /api/v1/toko-saya` (`TolakTokoTest`).
  - **Latihan Mandiri #7 & #8:** Memperbarui `docs/keputusan-sesi.md`, kontrak lintas domain pada `docs/domain/`, dan variabel kunci per akun contoh beserta skrip otomatis pada `docs/postman/Lapak-v1.postman_collection.json`.
- **Verifikasi:** Seluruh perubahan diverifikasi secara mandiri menggunakan `./vendor/bin/pint --test`, `php artisan migrate:fresh --seed`, dan `./vendor/bin/pest` (100% hijau).
