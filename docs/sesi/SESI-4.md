# Papan Sesi 4: Keamanan Transaksi

**Integrator:** Dimas (Fauzy Akhbar)
**Fokus:** Checkout Atomik, Kunci Baris, Idempotensi, Webhook Bertanda Tangan, dan Uji Balapan pada MySQL.

## Sasaran Sesi 4
1. Menyebut lima cara transaksi belanja rusak dan penangkalnya.
2. Membungkus pekerjaan banyak penulisan ke dalam satu transaksi (`DB::transaction`).
3. Mengamankan pola baca-putuskan-tulis dengan kunci baris (`lockForUpdate`).
4. Menerapkan Idempotency-Key pada checkout dan penyesuaian stok.
5. Memeriksa webhook dengan tanda tangan HMAC.
6. Menulis uji balapan (*race condition*) pada MySQL.

## Jawaban atas Titipan Sesi 3
1. Latihan mandiri Sesi 3 (misal: `KedudukanPesanan`) belum dipakai di Sesi 4.
2. Setiap rute POST baru dari latihan Sesi 3 harus didaftarkan di uji klasifikasi rute POST (Langkah 11).

## Peta Unggahan Sesi 4 (Dua Belas PR)
*(Isi tabel ini nanti setelah semua anggota membuat PR)*

| PR | Unggahan | Cabang | Penulis | Isi | Peninjau | Menunggu |
|---|---|---|---|---|---|---|
| ... | ... | ... | ... | ... | ... | ... |

## Catatan Khusus Sesi 4
- **Wajib MySQL:** SQLite tidak bisa menguji `lockForUpdate()`. Semua uji balapan harus dijalankan di MySQL.
- **Urutan Kunci (Lock Order):** Tim harus menyepakati urutan kunci (lihat `docs/PETA-SKEMA.md`) untuk mencegah *deadlock*.