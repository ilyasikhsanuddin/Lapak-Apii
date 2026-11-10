# Papan Sesi 1: Fondasi Tim dan Repositori

Integrator: Ilyas. Tag penutup: `sesi-1`.

## Peta unggahan

| PR | Unggahan | Cabang | Penulis | Peninjau | Menunggu | Status |
|---|---|---|---|---|---|---|
| 1 | Pembuka | sesi-1/fondasi-ilyas | Kelimanya | Dua anggota | - | Selesai |
| 2 | A1 | sesi-1/kontrak-respons-dani | Dani | Ilyas | 1 | Selesai |
| 3 | A2 | sesi-1/kontrak-uang-vicky | Vicky | Fauzy | 1 | Selesai |
| 4 | A3 | sesi-1/kontrak-identitas-ilyas | Ilyas | Hilma | 1 | Selesai |
| 5 | A4 | sesi-1/kontrak-katalog-hilma | Hilma | Vicky | 4 | Selesai |
| 6 | A5 | sesi-1/kontrak-dompet-fauzy | Fauzy | Dani | 4 | Selesai |
| 7 | B1 | sesi-1/galat-dan-ci-dani | Dani | Ilyas | 2 | Selesai |
| 8 | B2 | sesi-1/registrasi-ilyas | Ilyas | Hilma | 4, 7 | Selesai |
| 9 | B3 | sesi-1/katalog-hilma | Hilma | Vicky | 3, 5, 7 | Selesai |
| 10 | B4 | sesi-1/belanja-vicky | Vicky | Fauzy | 3, 5, 7 | Selesai |
| 11 | B5 | sesi-1/buku-besar-fauzy | Fauzy | Dani | 2, 3, 6, 7 | Selesai |
| 12 | Penutup | sesi-1/penutup-ilyas | Ilyas | Hilma | 11 | Selesai |

Kolom Menunggu berisi nomor PR yang harus masuk lebih dulu.

## Catatan penutup

Kendala yang dihadapi:
- PR #1 sempat di-revert tidak sengaja sehingga `main` kosong. Dipulihkan lewat PR pemulihan dengan membatalkan revert-nya.
- Uji bentuk galat pada B2 merah sampai B1 (Dani) masuk `main`, sesuai ketergantungan di kolom Menunggu.

Titipan untuk integrator Sesi 2 (Hilma):
- Salin kerangka papan sesi ini menjadi `docs/sesi/SESI-2.md`.
- Perbarui `.github/CODEOWNERS` pada unggahan pembuka Sesi 2.
- Lakukan tinjauan serah terima: baca papan sesi dan jalankan `main` dari basis data kosong.