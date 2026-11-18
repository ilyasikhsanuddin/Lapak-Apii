## Sesi 3

| Bagian | Alat | Cara memverifikasi |
|---|---|---|
| Membaca PDF modul dan menyusun prompt kerja untuk A1 (PR 26) | Claude | Dicocokkan dengan Langkah 2, Bagian D, dan E.1 pada modul |
| Implementasi A1: enum NamaIzin dan NamaPeran, migration, model, PeranIzinSeeder, PemeriksaIzin, middleware izin, matriks otorisasi, dan KONTRAK-API.md | Claude (agen coding) | Menjalankan sendiri `php artisan migrate:fresh --seed`, `./vendor/bin/pint --test`, dan `./vendor/bin/pest` (192 lulus); memeriksa 18 berkas pada diff; mencocokkan tabel peran-izin dengan `NamaPeran::izinBawaan()` |
| Pembuktian uji kunci | Claude | Mematikan sementara `prependToPriorityList` dan menambah baris matriks palsu; kedua uji berubah merah, lalu dikembalikan dengan `git checkout` |

