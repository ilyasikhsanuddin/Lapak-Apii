# Papan Sesi 3 — Peran, Izin, dan Kewenangan atas Objek

## 1. Tujuan Sesi

Sesi 3 membahas penerapan Role-Based Access Control (RBAC), pengelolaan peran dan izin pengguna, serta pembatasan akses terhadap objek berdasarkan kewenangan pengguna.

## 2. Prinsip Otorisasi

1. Middleware auth:sesi memastikan pengguna sudah login.
2. Middleware izin memeriksa izin pengguna.
3. Pemeriksaan kepemilikan objek dilakukan setelah izin terpenuhi.

Ketentuan respons:
- Belum login: HTTP 401.
- Tidak memiliki izin: HTTP 403.
- Objek tidak ditemukan atau tidak dapat diakses: HTTP 404.

Prinsip utama:
- Satu rute menggunakan satu izin.
- Rute menyebutkan izin, bukan nama peran.
- Satu pengguna dapat memiliki beberapa peran.
- Izin efektif merupakan gabungan izin dari seluruh peran pengguna.
- Admin tidak otomatis memiliki izin buku besar.

## 3. Rancangan Matriks Peran dan Izin

Matriks awal berdasarkan modul dan menunggu kesepakatan tim.

| Peran | Izin |
|---|---|
| pembeli | keranjang.kelola, toko.buka |
| penjual | produk.kelola, dompet.lihat |
| admin | toko.moderasi, peran.kelola |
| keuangan | buku-besar.lihat |
| auditor | buku-besar.lihat |

## 4. Rancangan Endpoint API

| Endpoint | Metode | Izin |
|---|---|---|
| /saya | GET | Tanpa izin khusus |
| /admin/pengguna | GET | peran.kelola |
| /admin/pengguna/{id}/peran/{peran} | PUT, DELETE | peran.kelola |
| /toko-saya | GET, PATCH | Tanpa izin khusus |
| /toko-saya | POST | toko.buka |
| /toko-saya/produk | GET, POST | produk.kelola |
| /toko-saya/produk/{produk} | PATCH, DELETE | produk.kelola |
| /admin/toko | GET | toko.moderasi |
| /admin/toko/{id}/setujui | POST | toko.moderasi |
| /admin/toko/{id}/tangguhkan | POST | toko.moderasi |
| /keranjang | GET, DELETE | keranjang.kelola |
| /keranjang/butir/{produkId} | PUT, DELETE | keranjang.kelola |
| /dompet | GET | dompet.lihat |
| /dompet/mutasi | GET | dompet.lihat |
| /buku-besar/dompet | GET | buku-besar.lihat |
| /buku-besar/dompet/{id}/mutasi | GET | buku-besar.lihat |

Catatan: Daftar endpoint dan izin perlu disesuaikan dengan kesepakatan tim dan implementasi akhir.

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
| Penutup | Rani | Pengujian dan dokumentasi akhir |

## 6. Urutan Pull Request

1. PR pembuka — Rani.
2. PR A1 — Sekar.
3. PR A2 — Bambang.
4. PR B1 — Bambang.
5. PR B2 — Sekar.
6. PR B3 — Bagas.
7. PR B4 — Rani.
8. PR B5 — Dimas.
9. PR penutup — Rani.

Pengerjaan tahap B dimulai setelah A1 dan A2 digabungkan ke main.

## 7. Pengujian Integrasi

- composer install
- php artisan migrate:fresh --seed
- ./vendor/bin/pint --test
- ./vendor/bin/pest
- php artisan lapak:periksa-buku
- php artisan lapak:matriks-izin
- php artisan route:list --path=api/v1

## 8. Catatan Integrasi

- Setiap anggota bekerja melalui branch masing-masing.
- Pull request harus diperiksa reviewer.
- Perubahan main dilakukan melalui review dan merge.
- Pengujian dilakukan kembali setelah integrasi.
- Dokumen diperbarui mengikuti kesepakatan dan hasil implementasi tim.
