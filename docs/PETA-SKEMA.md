# Peta Skema Basis Data Lapak

Dokumen ini mencatat kepemilikan tabel, pola penomoran migration, dan arah ketergantungan foreign key pada proyek Lapak.

## 1. Kepemilikan Tabel per Domain

| Domain | Penanggung Jawab | Tabel |
|---|---|---|
| D1 - Identitas | Bambang / Ilyas | pengguna |
| D2 - Toko dan Katalog | Bagas / Hilma | toko, kategori, produk |
| D3 - Keranjang dan Pesanan | Vicky | keranjang, butir_keranjang, pesanan, butir_pesanan |
| D4 - Pembayaran dan Saldo | Dimas / Fauzy | dompet, mutasi_saldo, pembayaran, pencairan |
| D5 - Otorisasi, Audit, dan Kualitas | Sekar / Dani | peran, izin, kunci_idempotensi, log_audit |

Catatan: Pembagian nama anggota disesuaikan dengan tim pelaksana. Vicky bertanggung jawab atas D3.

## 2. Pola Penomoran Migration

Nama berkas migration menggunakan pola:

`2026_10_SS_DNNNNN_nama.php`

Keterangan:
- SS: nomor sesi.
- D: nomor domain.
- NNNNN: nomor urut migration dalam domain.

Contoh:

`2026_10_01_200003_buat_tabel_produk.php`

Berkas tersebut merupakan migration ketiga milik domain D2 pada Sesi 1.

Penomoran migration mengikuti urutan domain, bukan urutan anggota menjalankan perintah make:migration. Dengan demikian, nama migration antaranggota tidak bertabrakan.

## 3. Arah Ketergantungan Foreign Key

Arah ketergantungan antardomain pada Sesi 1:

- pengguna (D1) menjadi acuan bagi toko (D2).
- toko (D2) menjadi acuan bagi produk (D2).
- produk (D2) menjadi acuan bagi butir_keranjang (D3).
- pengguna (D1) menjadi acuan bagi keranjang (D3).
- pengguna (D1) menjadi acuan bagi dompet (D4).
- dompet (D4) menjadi acuan bagi mutasi_saldo (D4).

Aturan: Foreign key hanya boleh menunjuk ke domain bernomor sama atau lebih kecil.

## 4. Gambaran Relasi Domain

```text
pengguna (D1)
  |
  +---- toko (D2)
  |       |
  |       +---- produk (D2)
  |                |
  |                +---- butir_keranjang (D3)
  |
  +---- keranjang (D3)

pengguna (D1)
  |
  +---- dompet (D4)
           |
           +---- mutasi_saldo (D4)
```

## 5. Kontrak Tanpa Tabel

| Kontrak | Pemilik | Pengguna |
|---|---|---|
| ResponsApi | D5 | Semua domain |
| GalatDomain | D5 | Semua domain |
| Uang | D3 | D2, D3, dan D4 |

## 6. Catatan Domain D3

Domain D3 bertanggung jawab atas:
- Keranjang belanja.
- Rincian barang dalam keranjang.
- Pesanan.
- Rincian barang dalam pesanan.
- Perhitungan belanja dan simulasi belanja.

Detail struktur tabel dan implementasi D3 dijelaskan pada `docs/domain/D3-belanja.md`.
