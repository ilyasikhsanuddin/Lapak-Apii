# Daftar Periksa Rilis Lapak

## Sebelum menandai kandidat

- [ ] Semua PR pembuka, kontrak, dan implementasi pada [papan sesi](sesi/SESI-5.md) telah masuk ke `main` dengan merge commit setelah dua persetujuan.
- [ ] Pekerjaan CI `kualitas` dan `kualitas-mysql` hijau di `main`.
- [ ] Klon integrator bersih dan `git log origin/main..main` kosong sesudah menarik perubahan terbaru.
- [ ] Kandidat ditandai dengan tag beranotasi seperti `v1.0.0-rc.1` pada commit `main` yang diverifikasi. Tag diunggah dengan menyebut namanya.

## Verifikasi kandidat pada klon baru

- [ ] Klon tag kandidat, jalankan `composer install`, buat `.env` dari `.env.example`, dan buat kunci aplikasi. Jalankan `php artisan migrate:fresh --seed` pada basis data MySQL percobaan yang kosong.
- [ ] Jalankan `./vendor/bin/pint --test` dan `./vendor/bin/pest`. Ulangi Pest pada MySQL dengan basis data uji terpisah.
- [ ] Jalankan `php artisan lapak:balapan --semua` pada MySQL. Pastikan keempat belas skenario yang dinyatakan modul tersedia dan lulus.
- [ ] Jalankan `php artisan lapak:periksa-buku` dan `php artisan lapak:verifikasi-audit` sesudah balapan. Simpan jangkar di luar basis data.
- [ ] Siapkan basis data ketiga pada tag `sesi-4` dengan satu pesanan yang sudah dibayar. Pindah ke tag kandidat, jalankan `php artisan migrate --force` dan `php artisan db:seed --class=PeranIzinSeeder --force`, lalu selesaikan pesanan lama. Periksa buku besar dan log audit.
- [ ] Jalankan alur pesanan sampai pencairan melalui HTTP, periksa jawaban untuk permintaan yang harus ditolak, dan jalankan uji penutup untuk izin, rute POST, dan peristiwa audit.
- [ ] Catat temuan untuk pemilik domain. Setelah PR perbaikan masuk ke `main`, beri tag kandidat baru dan ulangi verifikasi. Tag yang sudah diunggah tetap pada commit asalnya.

## Sebelum menandai versi final

- [ ] `CHANGELOG.md` masuk melalui PR tersendiri ke `main`. Susun dari riwayat Git dan sertakan perubahan API, perubahan kompatibilitas, serta langkah naik versi untuk pengelola server.
- [ ] `config/lapak.php`, entri teratas `CHANGELOG.md`, dan `/api/v1/health` menyebut versi yang sama. `RilisTest` lulus.
- [ ] README memuat pemasangan, pengujian, dan cara naik versi. Papan sesi memuat hasil verifikasi dan batasan. `docs/ATURAN-BISNIS.md` menunjukkan tempat penegakan setiap aturan.
- [ ] Setelah PR perbaikan dan catatan perubahan masuk, rebase `sesi-5/rilis-dani` ke `origin/main` dan jalankan kembali uji penutup. PR rilis mendapat dua persetujuan, kedua pemeriksaan CI lulus, dan PR masuk melalui merge commit.
- [ ] Tag beranotasi `sesi-5` dan `v1.0.0` menunjuk commit merge rilis yang sama di `main`. Unggah kedua tag secara eksplisit.
- [ ] Terbitkan GitHub Release dari tag `v1.0.0` dengan entri `CHANGELOG.md` versi tersebut. Jalankan verifikasi akhir dan `scripts/rekap-sesi.sh 5`.
