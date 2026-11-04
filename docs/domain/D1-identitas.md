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