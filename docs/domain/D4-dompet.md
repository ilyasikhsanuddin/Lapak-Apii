# Domain D4: Pembayaran dan Saldo

Pemilik: Fauzy Akhbar (Dimas).

## Pertanyaan yang dijawab

Di mana setiap rupiah berada saat ini?

## Tabel

| Tabel | Sesi | Keterangan |
|---|---|---|
| dompet | 1 | Buku akun saldo (eksternal, escrow, pendapatan, penjual). |
| mutasi_saldo | 1 | Catatan mutasi berpasangan (double-entry ledger) berjumlah nol. Hanya append-only. |
| pembayaran | 4 | Rekaman pembayaran checkout pembeli via payment gateway. |
| pencairan | 5 | Pengajuan dan eksekusi pencairan saldo penjual ke rekening bank. |

## Berkas milik domain ini

- `app/Models/Dompet.php`
- `app/Models/MutasiSaldo.php`
- `app/Services/BukuBesar.php`
- `app/Console/Commands/PeriksaBuku.php`
- `tests/Feature/Dompet/`
- `database/migrations/2026_10_01_400001_buat_tabel_dompet.php`
- `database/migrations/2026_10_01_400002_buat_tabel_mutasi_saldo.php`
- `database/seeders/DompetSeeder.php`

## Aturan bisnis terkait

- **LP-1**: Seluruh nilai uang adalah bilangan bulat rupiah. Tidak ada tipe pecahan di kolom basis data, kode, maupun JSON.
- **LP-8**: Komisi marketplace 5% dari subtotal produk dipotong saat dana dilepas dari escrow ke penjual.
- **LP-12**: Dana pembeli ditahan di escrow dan dilepas ke penjual setelah konfirmasi terima atau otomatis 3 hari setelah dikirim.
- **LP-13**: Saldo hanya berubah lewat pemindahan antardompet yang tercatat sebagai sepasang mutasi berjumlah nol. Mutasi tidak diubah dan tidak dihapus. Hanya dompet eksternal yang boleh bernilai negatif.
- **LP-16**: Pencairan saldo minimal Rp 50.000, disetujui peran keuangan, dan penyetuju tidak boleh orang yang sama dengan pengaju.

## Arah Ketergantungan Foreign Key

```text
pengguna (D1) <-- dompet (D4) <-- mutasi_saldo (D4)
```

Kontrak tanpa tabel yang digunakan:
- `App\Domain\Uang` (D3): Value object representasi rupiah.
- `App\Http\Concerns\ResponsApi` (D5): Format amplop respons standar.
- `App\Exceptions\GalatDomain` (D5): Induk exception aturan bisnis domain.

## Tanggung Jawab pada Sesi 1

- **PR A5**: Skema tabel `dompet` dan `mutasi_saldo`, enum `JenisDompet` dan `JenisMutasi`, serta model Eloquent dengan pelindung modifikasi (`MutasiSaldo::booted`).
- **PR B5**: Service `BukuBesar` dengan transaksi atomik dan penguncian baris (`lockForUpdate`), perintah konsol audit `lapak:periksa-buku`, `DompetSeeder`, serta pengujian lengkap feature `BukuBesarTest`.

## Usulan Mitigasi Penjaga Basis Data (Latihan Mandiri 7)

Penjaga pada level Eloquent (`booted()`) dapat dilewati jika kueri dieksekusi melalui raw SQL atau `query()->update()`. Mitigasi yang direkomendasikan pada level basis data:
1. **Trigger Basis Data**: Membuat `BEFORE UPDATE` dan `BEFORE DELETE` trigger pada tabel `mutasi_saldo` yang membangkitkan `SIGNAL SQLSTATE '45000'` untuk membatalkan modifikasi baris.
2. **Hak Akses Basis Data (Database Privileges)**: Pengguna basis data aplikasi hanya diberikan hak `SELECT` dan `INSERT` pada tabel `mutasi_saldo`, tanpa hak `UPDATE` atau `DELETE`.
