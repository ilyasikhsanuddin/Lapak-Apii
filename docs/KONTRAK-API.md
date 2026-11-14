# Kontrak API Lapak v1

Semua endpoint menggunakan awalan `/api/v1` dan menerima serta mengembalikan JSON.

## Kode Galat

| Kode | HTTP | Keterangan |
|---|---:|---|
| AKUN_DITANGGUHKAN | 403 | Akun pengguna sedang ditangguhkan. Hubungi admin. |
| KERANJANG_PENUH | 422 | Keranjang telah mencapai batas jumlah butir yang diizinkan. |

## Autentikasi

Endpoint terlindung memerlukan header:

`Authorization: Bearer {kunci_sesi}`

Kunci sesi diperoleh melalui `POST /api/v1/masuk`. Kunci harus dijaga kerahasiaannya.

## Endpoint Identitas dan Sesi

| Metode | Endpoint | Keterangan | Akses |
|---|---|---|---|
| POST | `/api/v1/masuk` | Masuk dan memperoleh kunci sesi | Publik |
| GET | `/api/v1/saya` | Melihat identitas pengguna aktif | Terlindung |
| POST | `/api/v1/keluar` | Mengakhiri sesi aktif | Terlindung |
| GET | `/api/v1/sesi` | Melihat daftar sesi pengguna | Terlindung |
| POST | `/api/v1/sesi/konfirmasi` | Mengonfirmasi sesi | Terlindung |
| DELETE | `/api/v1/sesi/{pengenal}` | Mencabut sesi | Terlindung, sesi segar |

## Endpoint Katalog

| Metode | Endpoint | Keterangan | Akses |
|---|---|---|---|
| GET | `/api/v1/kategori` | Daftar kategori | Publik |
| GET | `/api/v1/produk` | Daftar produk | Publik |
| GET | `/api/v1/toko/{slug}` | Detail toko | Publik |
