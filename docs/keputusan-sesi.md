# Keputusan Sesi 3: RBAC Berbasis Izin, Gate::before, dan Policy

## Persamaan dengan `spatie/laravel-permission`

Rancangan otorisasi Lapak dan paket `spatie/laravel-permission` sama-sama memodelkan kontrol akses berbasis peran dan izin (*RBAC*) menggunakan empat tabel relasi (`peran`, `izin`, `peran_izin`, dan `pengguna_peran`) sehingga satu pengguna dapat memegang lebih dari satu peran dan izinnya merupakan gabungan dari seluruh peran tersebut[cite: 6, 15, 73]. Keduanya juga menyandarkan pemeriksaan gerbang pada middleware rute berbasis nama izin (bukan nama peran), menyimpan hasil pembacaan izin di *cache* untuk menghindari kueri berulang, serta menghapus *cache* tersebut ketika peran atau pemetaan izin berubah[cite: 5, 18, 39, 73].

## Mengapa Lapak Tidak Memakai `Gate::before` untuk Admin

`Gate::before` yang mengembalikan `true` bagi peran `admin` akan menjadikan admin sebagai *super admin* yang otomatis lolos seluruh pemeriksaan izin dan *Policy*[cite: 6, 73]. Lapak sengaja menolaknya demi menegakkan **asas pemisahan tugas** (*separation of duties*)[cite: 6, 73]:

1. **Membatasi dampak peretasan satu akun:** Tidak ada satu peran pun yang memegang seluruh izin; admin mengurus pengguna dan moderasi toko (`peran.kelola`, `toko.moderasi`), tetapi tidak memiliki izin melihat uang (`buku-besar.lihat`) maupun berbelanja/berjualan memakai akun dinasnya (`keranjang.kelola`, `toko.buka`)[cite: 6, 8, 9, 15, 73].
2. **Mencegah pengabaian aturan benturan kepentingan:** Jika `Gate::before` meloloskan admin pada semua kemampuan, pemeriksaan *Policy* tingkat objek ikut terlewati sehingga admin dapat mengubah perannya sendiri atau menyetujui tokonya sendiri[cite: 7, 73].

## Aturan Lapak yang Memerlukan *Policy* (Tidak Cukup dengan Peran Saja)

Peran dan middleware `izin:<nama>` hanya menjawab jenis tindakan yang boleh dilakukan secara umum, tetapi tidak mengetahui siapa pemilik atau sasaran objek yang sedang diakses[cite: 5, 6, 73]. Lapak membutuhkan *Policy* untuk menegakkan larangan **benturan kepentingan** atas objek tertentu (dan mengembalikan `403 DITOLAK` karena pemanggil memang berhak mengetahui objek tersebut ada)[cite: 6, 7, 73]:

- **`PenggunaPolicy::ubahPeran`:** Pemegang izin `peran.kelola` tetap dilarang mengubah perannya sendiri (`$pelaku->is($sasaran)`), sehingga admin tidak dapat menambahkan peran `keuangan` ke akunnya sendiri maupun mencabut status adminnya sendiri[cite: 7, 32, 33, 73].
- **`TokoPolicy::moderasi`:** Pemegang izin `toko.moderasi` tetap dilarang menyetujui, menolak, atau menangguhkan toko miliknya sendiri (`$toko->pemilik_id !== $pelaku->id`) apabila akun admin tersebut juga mendaftar sebagai pemilik toko[cite: 7, 48, 73].
