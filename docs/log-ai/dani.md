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
