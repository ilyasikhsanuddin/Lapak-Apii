# Domain D1: Identitas dan Sesi


Pemilik: Ilyas.

## Pertanyaan yang dijawab

Siapa yang sedang berbicara dengan sistem, dan apa buktinya?

## Tabel

| Tabel | Sesi | Keterangan |
|---|---|---|
| pengguna | 1 | Akun pengguna. Satu akun boleh memegang lebih dari satu peran. |
| sesi | 2 | Kunci sesi buatan sendiri, disimpan sebagai hash. |

## Berkas milik domain ini

- `app/Models/Pengguna.php`
- `app/Http/Controllers/RegistrasiController.php`
- `routes/api/identitas.php`
- `tests/Feature/Identitas/`
- `config/auth.php`

## Aturan bisnis terkait

- LP-2: kewenangan ditentukan izin yang melekat pada peran, bukan nama peran.
- LP-3: satu pengguna memiliki paling banyak satu toko.
- LP-14 dan LP-15: kunci sesi dan sesi segar (dikerjakan pada Sesi 2).

## Tanggung jawab pada Sesi 1

- PR A3: tabel, model, factory pengguna, dan akun contoh.
- PR B2: endpoint registrasi beserta ujinya.
- Kata sandi tidak pernah muncul pada respons.

## Yang dibutuhkan dari domain lain

| Dari | Yang dibutuhkan | Dipakai untuk |
|---|---|---|
| D5 (Dani) | `PenanganGalatApi` dan bentuk galat kontrak (`sukses`, `galat.kode`, `galat.rincian`) | Respons 422 validasi dan 429 pembatasan laju berbentuk kontrak |
| D5 (Dani) | Middleware `PaksaJson` dan `IdPermintaan` | Semua rute API menjawab JSON dan membawa id permintaan |
| D5 (Dani) | `ResponsApi` (metode `sukses()` pada `Controller`) | Amplop respons registrasi |
| D5 (Dani) | `config/lapak.php` dan `bootstrap/app.php` | Konfigurasi dan pemuat rute per domain |

## Yang disediakan untuk domain lain

| Untuk | Yang disediakan | Keterangan |
|---|---|---|
| D2, D3, D4 | Tabel `pengguna` (migration `2026_10_01_100001`) | Dirujuk foreign key `toko.pemilik_id`, `keranjang.pengguna_id`, dan dompet pengguna |
| Semua | Model `Pengguna`, `PenggunaFactory`, enum `StatusAkun` | Dipakai uji dan seeder domain lain |
| D2 | Akun contoh dari `PenggunaSeeder` (misalnya `sri@lapak.test`) | `KatalogSeeder` mencari pemilik toko berdasarkan surel |
| Semua | Provider autentikasi `Pengguna` pada `config/auth.php` | Guard sesi Sesi 2 bergantung padanya |
| Klien | Endpoint `POST /api/v1/registrasi` dan `PenggunaResource` | Satu-satunya pintu tulis pada Sesi 1 |
