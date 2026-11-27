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

## Otorisasi

- Kewenangan ditentukan izin yang melekat pada peran, bukan nama peran (LP-2). Satu akun boleh
  memegang beberapa peran; izinnya adalah gabungan izin seluruh perannya.
- Rute terlindung menyebut paling banyak satu izin: `->middleware('izin:<nama>')`. Bila dua peran
  membutuhkan pintu yang sama, keduanya diberi izin yang sama. Rute tidak pernah menyebut nama peran.
- Setiap rute terlindung memiliki tepat satu baris pada `tests/Feature/Sistem/matriks/<domain>.php`.
  Rute yang terbuka bagi siapa pun yang sudah masuk ditulis `'*'`, tidak dibiarkan tanpa baris.
- Urutan pemeriksaan selalu sama: kunci sah (401), memegang izin (403 `DITOLAK`), sesi segar bila
  rutenya menuntut (403 `LP-15`). Baru setelah ketiganya lolos, masukan divalidasi dan objek dicari.
  Orang yang tidak berwenang karenanya menerima 403 yang sama untuk nomor yang ada dan yang tidak ada.
- Pertanyaan "objek yang ini boleh?" dijawab di dalam controller: kueri berlingkup (objek milik
  orang lain dijawab 404) atau Policy (403 `DITOLAK`). Lihat `docs/domain/` untuk pilihan tiap domain.
- Badan 403 `DITOLAK` selalu sama dan tidak menyebut izin apa yang kurang.
- `GET /saya` menyertakan `pengguna.peran` dan `izin`. Klien memakainya untuk menata tampilan saja;
  server memutuskan ulang pada setiap permintaan.
- Peran hanya berubah lewat `App\Services\PengelolaPeran`. Setiap perubahan mencabut seluruh sesi
  akun itu. Klien yang tiba-tiba menerima 401 mengarahkan penggunanya masuk ulang.

| Peran | Izin |
| --- | --- |
| `pembeli` | `keranjang.kelola`, `toko.buka` |
| `penjual` | `produk.kelola`, `dompet.lihat` |
| `admin` | `toko.moderasi`, `peran.kelola` |
| `keuangan` | `buku-besar.lihat` |
| `auditor` | `buku-besar.lihat` |

Tabel di atas disalin dari `App\Enums\NamaPeran::izinBawaan()`, satu-satunya tempat pemetaan ini
ditulis. `PeranIzinSeeder` menyalinnya ke basis data. Tidak ada peran yang memegang seluruh izin.

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

## Idempotensi

- Setiap permintaan yang mengubah uang atau stok wajib membawa header `Idempotency-Key` (LP-11):
  16 sampai 64 huruf, angka, garis bawah, atau tanda hubung. UUID memenuhi syarat ini.
- Klien membuat SATU kunci untuk satu niat, misalnya satu kali menekan tombol bayar, dan memakai
  kunci yang sama setiap kali mengulang permintaan itu karena jaringan putus atau batas waktu habis.
- Permintaan pertama yang berhasil (2xx) dicatat bersama responsnya. Pengulangan dengan kunci dan
  badan yang sama menerima respons yang sama, ditandai header `Idempotency-Replayed: true`, dan
  tidak berefek lagi.
- Permintaan yang gagal tidak meninggalkan catatan. Setelah sebabnya dibetulkan, kunci yang sama
  boleh dipakai lagi.
- Kunci yang sama dengan badan yang berbeda dijawab 422 `LP-11`. Kunci berlaku per pengguna.
- Catatan kunci dan seluruh perubahan data berada dalam satu transaksi basis data. Rute yang
  memasang `idempoten` karenanya tidak boleh memanggil layanan luar di dalam controller-nya.
- Pada pukul 03:30, `lapak:pangkas-idempotensi` menghapus catatan yang lebih tua dari masa simpan
  yang dikonfigurasi, dengan nilai bawaan tujuh hari. Kunci yang dipakai kembali setelah
  catatannya dihapus diproses sebagai permintaan baru.

## Webhook pembayaran

- `POST /webhook/pembayaran` adalah satu-satunya rute tulis tanpa kunci sesi. Pemanggilnya
  gerbang pembayaran. Pembeli tidak memiliki pintu apa pun untuk menandai tagihannya lunas.
- Gerbang mengirim header `X-Gateway-Signature: t=<detik unix>,v1=<tanda>`, dengan `<tanda>`
  berupa HMAC-SHA256 atas teks `<t>.<badan mentah>` memakai rahasia bersama `LAPAK_GATEWAY_RAHASIA`.
- Tanpa header, rahasia salah, badan diubah, atau selisih waktu lebih dari 5 menit: jawabannya
  selalu 401 `TANDA_TANGAN_SALAH` yang sama. Rahasia yang kosong menolak seluruh webhook.
- Badan: `id_peristiwa`, `kode_pembayaran`, `jumlah_rupiah`. Jumlah dicocokkan dengan tagihan di
  basis data; selisih satu rupiah pun dijawab 422 `JUMLAH_TIDAK_COCOK` dan tidak melunasi apa pun.
- Kabar yang sama boleh tiba berkali-kali. Yang pertama dijawab `"hasil": "dilunasi"`, sisanya
  `"hasil": "sudah_diproses"`, dan uangnya dibukukan satu kali.

## Dana dan Pencairan

- Dana pembayaran pembeli ditampung di dompet sistem `escrow` sampai pesanan diselesaikan (LP-12).
- Ketika pesanan selesai (`LayananPesanan::terima`), dana escrow dilepas:
  - Komisi dipindahkan ke dompet sistem `pendapatan` sebesar persentase komisi dari subtotal produk (LP-8).
  - Sisa total pesanan (subtotal dikurangi komisi ditambah ongkir) dipindahkan ke dompet `penjual`.
- Ketika pesanan yang sudah dibayar dibatalkan, seluruh dana pesanan di escrow dikembalikan ke dompet `eksternal` (pembeli).
- Tagihan pembayaran dapat dibatalkan oleh pembeli sebelum pembayaran lunas, menandai status pembayaran menjadi `dibatalkan`.
- Pengajuan pencairan saldo oleh penjual menahan saldo dari dompet penjual ke dompet sistem `pencairan` dengan status `menunggu` (LP-16).



## Daur Hidup Pesanan

Pesanan memiliki alur status berikut:

1. `menunggu_pembayaran`: pesanan dibuat dan menunggu pembayaran.
2. `dibayar`: pembayaran telah dikonfirmasi oleh sistem.
3. `dikemas`: penjual mulai menyiapkan pesanan.
4. `dikirim`: penjual mengirim pesanan dan mengisi nomor resi.
5. `selesai`: pembeli atau sistem mengonfirmasi pesanan selesai.
6. `dibatalkan`: pesanan dibatalkan oleh pihak yang berwenang.

Perubahan status setelah checkout dilakukan melalui `LayananPesanan`.
Setiap perubahan status mengikuti aturan transisi LP-10 dan dilakukan
di dalam transaksi yang dilindungi `GembokCheckout`.

Informasi proses yang tersedia pada respons pesanan:

- `dibayar_pada`: waktu pembayaran dikonfirmasi.
- `dikemas_pada`: waktu pesanan mulai dikemas.
- `dikirim_pada`: waktu pesanan dikirim.
- `nomor_resi`: nomor pelacakan pengiriman.
- `selesai_pada`: waktu pesanan diselesaikan.
- `dibatalkan_pada`: waktu pesanan dibatalkan.
- `dibatalkan_oleh`: pihak yang melakukan pembatalan.
- `alasan_batal`: alasan pembatalan.

Pembatalan pesanan yang sudah dibayar mengembalikan dana melalui
`LayananDana` dan mengembalikan stok produk terkait. Pembatalan checkout
yang kedaluwarsa dijalankan oleh `PembatalanOtomatis` melalui
`LayananPesanan`.

## Log audit

- `GET /api/v1/audit` menampilkan daftar baris log audit terbaru lebih dulu (dapat disaring menurut
  `peristiwa`, `sasaran`, dan `pelaku_id`), dan `GET /api/v1/audit/rantai` menampilkan ujung serta
  panjang rantai saat ini (`jumlah`, `hash_terakhir`, `jangkar`).
- Kedua endpoint hanya terbuka bagi pemegang izin `audit.lihat` dengan sesi segar (`LP-15`), dan
  tidak ada endpoint tulis di bawah `/api/v1/audit` (`LP-17`).
- Jejak hanya ditulis oleh `App\Services\PencatatAudit::catat()` di dalam transaksi yang sama
  dengan tindakannya, sebagai langkah terakhir sebelum transaksi selesai, sambil mengunci baris
  kepala `rantai_audit` (`lockForUpdate()`) agar penulisan serempak tidak bercabang.
- Rincian yang memuat kunci rahasia (`kata_sandi`, `kunci`, `hash_kunci`, `rahasia`,
  `tanda_tangan`) atau bilangan pecahan ditolak di pintu, dan baris log tidak dapat diubah atau
  dihapus lewat model.
- Keutuhan seluruh rantai diverifikasi dengan `php artisan lapak:verifikasi-audit [--jangkar=...]`,
  termasuk secara otomatis setiap dini hari lewat penjadwal dan pada jalur CI.
