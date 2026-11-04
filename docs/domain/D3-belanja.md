# Piagam Domain D3 - Keranjang dan Pesanan

## 1. Identitas Domain

- Kode Domain: D3
- Nama Domain: Keranjang dan Pesanan
- Penanggung Jawab: Vicky Rinoval Ikhsan
- Repositori: Lapak-Apii

## 2. Tujuan Domain

Domain D3 bertanggung jawab mengelola keranjang belanja dan pesanan pengguna pada marketplace Lapak.

Domain ini menjawab pertanyaan: berapa jumlah yang harus dibayar oleh pembeli dan pesanan mana yang sah.

## 3. Kepemilikan Tabel

Domain D3 memiliki tabel:

1. `keranjang`
2. `butir_keranjang`
3. `pesanan`
4. `butir_pesanan`

Domain D3 bertanggung jawab atas migration, model, logika bisnis, endpoint, dan pengujian yang berkaitan dengan bagian tersebut.

## 4. Yang Dibutuhkan dari Domain Lain

| Domain | Kebutuhan |
|---|---|
| D1 - Identitas | Data pengguna sebagai pemilik keranjang dan pesanan. |
| D2 - Toko dan Katalog | Data produk, harga, stok, dan informasi toko untuk proses simulasi belanja. |
| D5 - Otorisasi, Audit, dan Kualitas | Kontrak respons API dan penanganan galat domain yang digunakan bersama. |

Domain D3 juga menggunakan kontrak `Uang` yang menjadi bagian dari domain D3 dan dipakai oleh D2, D3, serta D4.

## 5. Yang Disediakan untuk Domain Lain

| Domain | Layanan atau Kontrak |
|---|---|
| D2 - Toko dan Katalog | Kontrak `Uang` untuk menangani nilai uang dalam rupiah. |
| D4 - Pembayaran dan Saldo | Kontrak `Uang` untuk kebutuhan perhitungan nilai uang. |
| Semua domain | Mengikuti kontrak bersama yang disepakati dalam repositori. |

## 6. Aturan dan Batasan Domain

1. Nilai uang menggunakan bilangan bulat rupiah.
2. Harga tidak disimpan pada tabel `butir_keranjang`.
3. Harga produk dibaca dari basis data ketika proses perhitungan belanja dilakukan.
4. Harga dan total belanja dihitung oleh server, bukan mempercayai angka dari klien.
5. Satu checkout menghasilkan satu pesanan untuk setiap toko.
6. Rincian barang dalam keranjang memiliki hubungan dengan keranjang dan produk.
7. Foreign key hanya boleh menunjuk ke domain bernomor sama atau lebih kecil.

## 7. Ketergantungan Foreign Key

- `keranjang` bergantung pada `pengguna` milik D1.
- `butir_keranjang` bergantung pada `keranjang` milik D3 dan `produk` milik D2.

Urutan migration harus memperhatikan ketersediaan tabel yang menjadi acuan foreign key.

## 8. Catatan Pengembangan

Pada Sesi 1, pekerjaan D3 mencakup skema keranjang, model, perhitungan belanja, simulasi belanja, dan pengujian.

Endpoint keranjang yang terikat pada pengguna baru dikembangkan pada Sesi 2.

Implementasi D3 harus menjaga agar logika perhitungan belanja berada di `App\Domain\Belanja`, bukan dicampur dengan controller.
