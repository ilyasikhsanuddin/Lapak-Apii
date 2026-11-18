# Papan Sesi 2 — Identitas dan Session dengan Kunci

## Sasaran
Membuat mekanisme autentikasi sesi berbasis kunci (ULID + Random Bytes), guard sesi, pembatas laju, serta mengikat Toko, Produk, Keranjang, dan Dompet pada pemilik sesi.

## Urutan Merge Windows A (Kontrak)
1. PR #14 (A1 - Ilyas): Kunci Sesi, Tabel Sesi, Penerbit, dan Pemeriksa
2. PR #15 (A2 - Dani): Guard Sesi, Sesi Segar, Pembatas Laju
3. PR #16 (A3 - Hilma): Kepemilikan Toko & Toko Saya
4. PR #17 (A4 - Vicky): Lihat Keranjang
5. PR #18 (A5 - Fauzy): Saldo Dompet

## Urutan Merge Windows B (Implementasi)
1. PR #19 (B1 -l Ilyas): Masuk, Keluar, Sesi
2. PR #20 (B2 - Dani): Gerbang Rute & Pangkas Sesi
3. PR #21 (B3 - Hilma): Buka Toko & Kelola Produk
4. PR #22 (B4 - Vicky): Ubah Isi Keranjang
5. PR #23 (B5 - Fauzy): Riwayat Mutasi

## Catatan Penutup & Titipan
- Siapa pun yang masuk boleh membuka toko (persetujuan admin menyusul di Sesi 3).
- Kunci sesi perlu dirotasi saat peran berubah.

## Status Akhir & Penutup Sesi 2
- **Kontrak A1–A5:** Seluruh kontrak (PR #14 – #18) telah di-merge ke `main`.
- **Implementasi B1–B5:** Seluruh implementasi fitur (PR #19 – #23) telah di-merge ke `main`.
- **Latihan Mandiri Sesi 2:** Penyaring produk sendiri (`DaftarProdukSayaRequest`), penyaring mutasi dompet, dan catatan keranjang telah terintegrasi; seluruh 174 pengujian Pest hijau.
- **Verifikasi Integrator:** `php artisan migrate:fresh --seed`, `php artisan lapak:periksa-buku`, `./vendor/bin/pint --test`, dan `./vendor/bin/pest` lolos 100%.
