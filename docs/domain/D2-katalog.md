# Piagam Domain D2 — Toko dan Katalog

## Deskripsi
Mengelola data profil toko, kategori produk, dan pencarian katalog barang publik.

## Yang dibutuhkan dari domain lain
- **Domain D1 (Pengguna & Identitas):** `pemilik_id` pada tabel `toko` merujuk ke tabel `pengguna`. Perlu validasi surel/identitas pengguna untuk kepemilikan toko.
- **Domain D3 (Keranjang & Transaksi):** Data harga (`harga_rupiah`), stok, dan berat (`berat_gram`) produk untuk kalkulasi item belanjaan.

## Yang disediakan untuk domain lain
- **Scope Tampil (`Produk::scopeTampil`):** Menjamin seluruh domain publik hanya membaca produk berstatus aktif dari toko aktif (LP-4).
- **Resource & Snapshot Produk:** Data rincian produk dan toko untuk pelaporan mutasi/transaksi di Domain D3 dan D4.
