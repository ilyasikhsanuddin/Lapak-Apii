# Papan Sesi 3 — Peran, Izin, dan Kewenangan atas Objek

## 1. Tujuan Sesi

Sesi 3 menerapkan Role-Based Access Control (RBAC), pengelolaan peran dan izin pengguna, serta pembatasan akses terhadap objek berdasarkan kewenangan pengguna.

## 2. Prinsip Otorisasi

1. Middleware `auth:sesi` memastikan pengguna sudah memiliki sesi yang sah.
2. Middleware `izin:` memeriksa izin pengguna.
3. Pemeriksaan kepemilikan objek dilakukan setelah izin terpenuhi.
4. Satu pengguna dapat memiliki beberapa peran.
5. Izin efektif merupakan gabungan izin dari seluruh peran pengguna.
6. Route menyebutkan izin, bukan nama peran.
7. Admin tidak otomatis memiliki izin untuk mengakses uang atau buku besar.

Ketentuan respons:
- Belum login atau sesi tidak sah: HTTP 401.
- Tidak memiliki izin: HTTP 403 dengan kode `DITOLAK`.
- Objek tidak ditemukan: HTTP 404.
- Tindakan sensitif dengan sesi tidak segar: HTTP 403 dengan kode `LP-15`.

## 3. Matriks Peran dan Izin Implementasi

Pemetaan izin bawaan ditetapkan melalui `App\Enums\NamaPeran::izinBawaan()` dan disalin ke basis data oleh `PeranIzinSeeder`.

| Peran | Izin |
|---|---|
| pembeli | `keranjang.kelola`, `toko.buka` |
| penjual | `produk.kelola`, `dompet.lihat` |
| admin | `toko.moderasi`, `peran.kelola`, `akun.tangguhkan` |
| keuangan | `buku-besar.lihat` |
| auditor | `buku-besar.lihat` |

Admin sengaja tidak memiliki izin `dompet.lihat` maupun `buku-besar.lihat`. Keuangan dan auditor dapat membaca buku besar, tetapi tidak memperoleh kewenangan tulis dari izin tersebut.

## 4. Rancangan Endpoint API

Endpoint berikut menggambarkan kelompok akses berdasarkan implementasi route API v1.

| Kelompok endpoint | Metode / fungsi | Izin |
|---|---|---|
| `/api/v1/keranjang` | Mengelola keranjang | `keranjang.kelola` |
| `/api/v1/toko-saya` | Mengajukan pembukaan toko | `toko.buka` |
| `/api/v1/toko-saya/produk` | Mengelola produk toko | `produk.kelola` |
| `/api/v1/dompet` | Melihat dompet sendiri | `dompet.lihat` |
| `/api/v1/buku-besar` | Membaca buku besar | `buku-besar.lihat` |
| `/api/v1/admin/pengguna` | Mengelola peran pengguna | `peran.kelola` |
| `/api/v1/admin/pengguna` | Menangguhkan dan memulihkan akun | `akun.tangguhkan` |
| `/api/v1/admin/toko` | Memoderasi toko | `toko.moderasi` |

Rute terlindung menggunakan middleware `auth:sesi`. Rute yang memerlukan izin menggunakan middleware `izin:` setelah autentikasi sesi.

## 5. Pembagian Tugas Sesi 3

| Tahap | Penanggung Jawab | Pekerjaan |
|---|---|---|
| Pembuka | Rani | Papan sesi dan CODEOWNERS |
| A1 | Sekar | Kontrak izin, peran, middleware |
| A2 | Bambang | Pengelola peran |
| B1 | Bambang | Pengelolaan peran pengguna |
| B2 | Sekar | Ingatan izin |
| B3 | Bagas | Moderasi toko |
| B4 | Rani | Gerbang akses keranjang |
| B5 | Dimas | Buku besar |
| Penutup | Rani | Pengujian integrasi dan dokumentasi akhir |

## 6. Urutan Pull Request

1. PR pembuka — Rani.
2. PR A1 — Sekar.
3. PR A2 — Bambang.
4. PR B1 — Bambang.
5. PR B2 — Sekar.
6. PR B3 — Bagas.
7. PR B4 — Rani.
8. PR B5 — Dimas.
9. PR Penutup — Rani.

Pengerjaan tahap B dimulai setelah A1 dan A2 digabungkan ke `main`.

## 7. Pengujian Integrasi

Pengujian integrasi dilakukan dengan perintah:

- `composer install`
- `php artisan migrate:fresh --seed`
- `./vendor/bin/pint --test`
- `./vendor/bin/pest`
- `php artisan lapak:periksa-buku`
- `php artisan lapak:matriks-izin`
- `php artisan route:list --path=api/v1`

Pengujian `IzinTerpakaiTest` memastikan:
1. Setiap izin yang didefinisikan pada `NamaIzin` digunakan oleh minimal satu route.
2. Tidak ada route yang menggunakan izin yang tidak dikenal.
3. Setiap route dengan middleware `izin:` juga menggunakan `auth:sesi`.

## 8. Catatan Integrasi dan Penutup

- Setiap anggota bekerja melalui branch masing-masing.
- Pull request diperiksa oleh reviewer sebelum digabungkan.
- Perubahan pada `main` dilakukan melalui review dan merge.
- Pengujian dijalankan kembali setelah integrasi.
- Dokumentasi diperbarui mengikuti implementasi akhir.

Hasil pengujian `IzinTerpakaiTest` pada branch Penutup: 3 pengujian berhasil.

Catatan LP-10: aturan perpindahan status pesanan telah tersedia pada `AturanTransisi` dan daftar status pada `StatusPesanan`. Konfigurasi batas pembayaran 24 jam tersedia pada `config/lapak.php`. Namun, proses otomatis pembatalan pesanan kedaluwarsa dan pengembalian stok belum ditemukan dalam implementasi yang diperiksa.
