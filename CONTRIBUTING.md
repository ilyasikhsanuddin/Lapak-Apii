# Panduan Kontribusi Tim Lapak

Dokumen ini mengikat alur kerja kolaborasi lima pengembang pada repositori Lapak.

## 1. Pembagian Domain dan Peran
- **D1 - Identitas dan Sesi**: Ilyas (Bambang) - `pengguna`, `sesi`
- **D2 - Toko dan Katalog**: Hilma (Bagas) - `toko`, `kategori`, `produk`
- **D3 - Keranjang dan Pesanan**: Vicky (Rani) - `keranjang`, `butir_keranjang`, `pesanan`, `butir_pesanan`
- **D4 - Pembayaran dan Saldo**: Fauzy (Dimas) - `dompet`, `mutasi_saldo`, `pembayaran`, `pencairan`
- **D5 - Otorisasi, Audit, dan Kualitas**: Dani (Sekar) - `peran`, `izin`, `kunci_idempotensi`, `log_audit`

## 2. Aturan Cabang dan Git
- Cabang utama adalah `main` dan terlindungi.
- Pola penamaan cabang:
  - Pembuka: `sesi-<N>/fondasi-<nama>`
  - Kontrak (Jendela A): `sesi-<N>/kontrak-<topik>-<nama>`
  - Implementasi (Jendela B): `sesi-<N>/<topik>-<nama>`
  - Penutup: `sesi-<N>/penutup-<nama>`
- Setiap anggota wajib mengatur Git lokal:
  - `git config --global pull.rebase true`
- Merge ke `main` menggunakan **Merge Commit** (bukan squash / rebase merge) oleh integrator sesi berjalan.
- Jangan gunakan `git push --force` pada cabang bersama. Gunakan `git pull --rebase` atau `git push --force-with-lease` pada cabang pribadi setelah rebase.

## 3. Jadwal Unggahan per Sesi
1. **Pembuka**: Kerangka fondasi, papan sesi, `.github/CODEOWNERS` (oleh Integrator).
2. **Jendela A (Kontrak)**: Migration skema, enum, model polos, value object, dan unit test kontrak (oleh masing-masing anggota).
3. **Jendela B (Implementasi)**: Logika bisnis, controller/endpoint, seeder, dan feature test (oleh masing-masing anggota).
4. **Penutup**: Pengisian papan sesi, rekap kontribusi, tagging sesi (oleh Integrator).

## 4. Siklus Peninjauan (Review Cycle) Sesi 1
- PR Ilyas ditinjau oleh Hilma
- PR Hilma ditinjau oleh Vicky
- PR Vicky ditinjau oleh Fauzy
- PR Fauzy ditinjau oleh Dani
- PR Dani ditinjau oleh Ilyas

Peninjau wajib menarik (*pull/checkout*) cabang rekan, menjalankan migrasi dan pengujian lokal (`./vendor/bin/pest`), serta memastikan gaya kode lolos (`./vendor/bin/pint --test`).

## 5. Berkas Bersama
- `routes/api.php`: Tidak disunting setelah fondasi. Rute per domain dibuat di `routes/api/<domain>.php`.
- `database/seeders/DatabaseSeeder.php`: Ditambahkan bertahap satu baris sesuai urutan arah foreign key.
- `bootstrap/app.php`: Hanya disentuh atas koordinasi pemilik D5.
- `docs/log-ai/<nama>.md`: Satu berkas log terpisah per anggota.

## 6. Standar Pull Request
Setiap PR harus menggunakan templat `.github/pull_request_template.md` dan memenuhi kriteria daftar periksa:
1. Rebase ke `origin/main` terbaru.
2. `./vendor/bin/pint --test` lolos tanpa peringatan.
3. `php artisan migrate:fresh --seed` berjalan sukses dari basis data kosong.
4. `./vendor/bin/pest` hijau (semua test lolos).
5. Tidak ada berkas kredensial, `.env`, atau `vendor/` ter-commit.
