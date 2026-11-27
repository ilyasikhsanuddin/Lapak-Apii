## Sesi 3

| Bagian | Alat | Cara memverifikasi |
|---|---|---|
| Membaca PDF modul dan menyusun prompt kerja untuk A1 (PR 26) | Claude | Dicocokkan dengan Langkah 2, Bagian D, dan E.1 pada modul |
| Implementasi A1: enum NamaIzin dan NamaPeran, migration, model, PeranIzinSeeder, PemeriksaIzin, middleware izin, matriks otorisasi, dan KONTRAK-API.md | Claude (agen coding) | Menjalankan sendiri `php artisan migrate:fresh --seed`, `./vendor/bin/pint --test`, dan `./vendor/bin/pest` (192 lulus); memeriksa 18 berkas pada diff; mencocokkan tabel peran-izin dengan `NamaPeran::izinBawaan()` |
| Pembuktian uji kunci | Claude | Mematikan sementara `prependToPriorityList` dan menambah baris matriks palsu; kedua uji berubah merah, lalu dikembalikan dengan `git checkout` |


## Sesi 4

| Bagian | Alat | Cara memverifikasi |
|---|---|---|
| Implementasi A1 (LP-11): migration `kunci_idempotensi`, model `KunciIdempotensi`, `GalatIdempotensi`, middleware `Idempoten`, alias di `bootstrap/app.php`, dan `IdempotenTest` | Claude | Menjalankan sendiri `php artisan migrate:fresh --seed`, `./vendor/bin/pint --test` (PASS, 211 berkas), dan `./vendor/bin/pest` (319 lulus); memeriksa 12 berkas pada `git diff origin/main...HEAD` |
| Perintah `lapak:balapan` dan `lapak:balapan-anak`, trait `PanggilHttp`, skenario `Serempak`. Bagian awal `Balapan.php` ditulis ulang AI mengikuti uraian modul karena zip pendamping tidak tersedia | Claude | Menjalankan `php artisan lapak:balapan serempak`: LULUS, 8 proses serentak; membandingkan dengan salinan lain memakai `fc` (identik, bukan berkas asli modul) |
| Bagian idempotensi pada `docs/KONTRAK-API.md` | Claude | Dicocokkan dengan perilaku `Idempoten.php` (header, 422 untuk kunci dipakai ulang, tidak ada catatan untuk permintaan gagal); diff diperiksa, satu bagian saja, tanpa duplikat |
| Pemeriksaan sebelum PR | Saya | Menjalankan sendiri seluruh perintah verifikasi di atas; memeriksa `git status` dan daftar berkas diff agar berkas B5 (`Pangkas*`), `.env`, dan `vendor/` tidak ikut; memastikan cabang sudah memuat `origin/main` terbaru; menyisipkan baris kosong pada kontrak dan memeriksa diff-nya |


## Sesi 4: B5 Pangkas idempotensi (Latihan Mandiri 6)

| Bagian | Alat | Cara memverifikasi |
|---|---|---|
| Perintah `lapak:pangkas-idempotensi` (opsi `--hari`, `--batch`, penghapusan per batch), migrasi indeks `created_at` pada `kunci_idempotensi`, dan `PangkasIdempotensiTest` (8 kasus) | Claude | Menjalankan sendiri `./vendor/bin/pint --test` (PASS) dan `./vendor/bin/pest` di SQLite (399 lulus) dan MySQL 8.4.3 (399 lulus); memeriksa `git diff origin/main --stat`: hanya 5 berkas |
| Jadwal harian pukul 03:30 pada `routes/console.php` dan butir masa simpan 7 hari pada `docs/KONTRAK-API.md` | Claude (usulan teks), saya (menyunting berkas) | `php artisan schedule:list` menampilkan job terjadwal; diff kontrak diperiksa, hanya menambah 5 baris dan tidak mengubah baris orang lain |
| Argumen mengapa checkout tetap aman setelah 7 hari, termasuk catatan bahwa rute `POST /toko-saya/produk/{id}/stok` tidak punya pengaman kedua | Claude | Dicocokkan dengan perilaku checkout (keranjang kosong → 422 `KERANJANG_KOSONG`, tagihan kedaluwarsa dalam 24 jam) |
| Pemeriksaan balapan setelah perubahan | Saya | Menjalankan `php artisan lapak:balapan --semua` di MySQL: 8 skenario LULUS. Kegagalan pertama (`ModelNotFoundException`) disebabkan `DB_DATABASE=lapak_uji` yang masih terpasang di terminal, bukan kode; diperbaiki lalu dijalankan ulang |
| Pemeriksaan sebelum PR | Saya | Memeriksa `git status` bersih, `git diff origin/main --stat` (tanpa `.env` dan `vendor/`), dan rebase ke `origin/main` terbaru (sudah mutakhir) |

## Sesi 5: B5 Rantai audit

| Bagian | Alat | Cara memverifikasi |
|---|---|---|
| Rebase B5, pemeriksa jangkar, rute auditor, matriks otorisasi, dan uji pemangkasan | Codex | Pint lulus. Pest meluluskan 462 uji dan 1.785 asersi pada SQLite dan MySQL 8.0.46. Migrasi dan seeder diuji pada basis data baru. |
| Pembandingan penguncian kepala rantai | Codex | Pada salinan terpisah dengan pencatat kontrak, 1 dari 12 proses tercatat. Pada B5, 12 dari 12 tercatat. Kesebelas skenario yang terdaftar lulus. Buku besar seimbang dan rantai berisi 20 baris valid setelah balapan. |

## Sesi 5: Draf rilis dan perbaikan CI

| Bagian | Alat | Cara memverifikasi |
|---|---|---|
| Uji penutup, daftar periksa, dan papan sesi | Codex | Pint lulus. Uji naik versi memakai pesanan berbayar dari `sesi-4` yang diselesaikan setelah migrasi dan penyemaian ulang izin. |
| Diagnosis PR #82 serta audit pencabutan sesi dan penyesuaian stok | Codex | Log GitHub Actions menunjukkan tujuh peristiwa tanpa pencatat. Setelah integrasi B1 dan perbaikan, 475 uji Pest lulus di SQLite dan MySQL. Uji regresi memeriksa rollback ketika pencatatan gagal. Kesebelas skenario balapan yang tersedia lulus. |
| Penyesuaian cabang PR #82 dengan alur rilis modul | Codex | Cocokkan Langkah 11.4, 11.5, 13.1, dan 13.2 pada modul. Pint lulus. Uji penutup di atas `7c6a6ac` gagal pada `sesi.dicabut` dan `stok.disesuaikan`. |
