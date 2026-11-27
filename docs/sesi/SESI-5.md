# Sesi 5 — Escrow, Saldo, Audit, dan Rilis

| Tahap | Pemilik | Status |
| --- | --- | --- |
| Pembuka | Sekar | PR #73 masuk ke `main` |
| A1 Kontrak audit | Sekar | PR #74 masuk ke `main` |
| A2 Kontrak dana dan pencairan | Dimas | PR #75 masuk ke `main` |
| A3 Kontrak daur hidup pesanan | Rani | PR #76 masuk ke `main` |
| B1 Jejak identitas | Bambang | PR #80 masuk ke `main` lewat `7c6a6ac`. Jejak `sesi.dicabut` untuk pencabutan manual masih perlu PR lanjutan |
| B2 Pesanan toko | Bagas | PR #77 masuk ke `main` |
| B3 Tindakan pembeli | Rani | PR #78 masuk ke `main` |
| B4 Pencairan | Dimas | PR #79 masuk ke `main` |
| B5 Rantai audit | Sekar | PR #81 lulus kedua pekerjaan CI dan masuk ke `main` lewat merge commit `dafb406` |
| P1 Jejak penyesuaian stok | Bagas | Perbaikan `ee03c36` sudah diuji dalam integrasi sementara dan menunggu PR tersendiri ke `main` |
| Catatan perubahan | Bambang | Entri `CHANGELOG.md` untuk 1.0.0 menunggu PR tersendiri ke `main` |
| Rilis | Sekar | PR #82 memuat uji penutup dan daftar periksa. Versi 1.0.0 dan `RilisTest` mengikuti setelah catatan perubahan masuk ke `main` |

## Validasi B5

Pint lulus. Pest meluluskan 462 uji dan 1.785 asersi pada SQLite dan MySQL 8.0.46. Pada MySQL, 11 skenario yang terdaftar lulus. Buku besar mencatat 24 mutasi dengan 0 pelanggaran dan 20 baris log audit lolos verifikasi. Dalam pembandingan audit serempak, pencatat kontrak menyimpan 1 dari 12 jejak dan pencatat dengan penguncian menyimpan 12 dari 12.

## Temuan dan prasyarat rilis

- Uji penutup pada draf rilis di atas `7c6a6ac` menemukan `sesi.dicabut` dan `stok.disesuaikan` tanpa pencatat. Perbaikan sesi tersedia di commit `7f139be` dan perbaikan stok di `ee03c36`. Masing-masing masuk ke `main` lewat PR perbaikan.
- Skenario `keranjang-ganda`, `pencairan-ganda`, dan `putusan-ganda` belum tersedia lewat `lapak:balapan --semua`. Dua kelas pencairan yang ada belum memakai kontrak `SkenarioBalapan`.
- Setelah PR perbaikan dan catatan perubahan masuk ke `main`, segarkan cabang rilis di atas `origin/main` dan ulangi uji penutup. Selanjutnya tetapkan versi `1.0.0` dan periksa kesesuaiannya dengan `CHANGELOG.md` dan `/api/v1/health`.

## Verifikasi perbaikan CI

Pemeriksaan awal PR #82 mendeteksi tujuh peristiwa tanpa pencatat. PR #80 membawa lima pencatat identitas ke `main`. Pada integrasi sementara di `86272a2`, perbaikan pencabutan sesi dan penyesuaian stok melengkapi dua pencatat yang tersisa. Kedua tindakan diuji untuk memastikan perubahan dibatalkan bila audit gagal. Pint lulus. Pest meluluskan 475 uji dan 1.852 asersi pada SQLite dan MySQL 8.0.46. Sebelas skenario balapan yang terdaftar lulus, buku besar seimbang, dan 50 baris log audit lolos verifikasi.

Urutan verifikasi kandidat dan publikasi dicatat dalam [daftar periksa rilis](../RILIS.md).

## Uji pendahuluan naik versi

Di klon baru `sesi-4` dengan MySQL 8.0.46, pesanan lama dibuat dan dibayar. Setelah berpindah ke commit B5 `6bef23f`, tiga migrasi berjalan dengan `migrate --force`. Pesanan dan sesi lama tetap dapat dibaca. Rute baru pesanan penjual menjawab 403 hingga `PeranIzinSeeder` dijalankan ulang. Dengan izin baru, penjual mengemas dan mengirim pesanan lama, lalu pembeli mengonfirmasi penerimaan. Buku besar seimbang dengan 18 mutasi. Rantai audit berisi satu jejak pelepasan dana yang valid. Uji ini masih perlu diulang pada tag kandidat resmi.
