<?php

declare(strict_types=1);

namespace App\Enums;

enum NamaPeran: string
{
    case Pembeli = 'pembeli';
    case Penjual = 'penjual';
    case Admin = 'admin';
    case Keuangan = 'keuangan';
    case Auditor = 'auditor';

    public function keterangan(): string
    {
        return match ($this) {
            self::Pembeli => 'Berbelanja dan mengajukan pembukaan toko',
            self::Penjual => 'Mengelola produk, memproses pesanan, dan melihat dompet tokonya',
            self::Admin => 'Memoderasi toko, mengelola peran, dan menangguhkan akun pengguna',
            self::Keuangan => 'Membaca buku besar; menyetujui pencairan mulai Sesi 5',
            self::Auditor => 'Membaca buku besar dan log audit tanpa hak tulis apa pun',
        };
    }

    /**
     * Izin bawaan tiap peran. Inilah satu-satunya tempat pemetaan ini ditulis;
     * PeranIzinSeeder menyalinnya ke basis data.
     *
     * Admin sengaja TIDAK memegang izin uang. Tidak ada peran yang boleh segalanya.
     *
     * @return list<NamaIzin>
     */
    public function izinBawaan(): array
    {
        // Satu izin per baris, sengaja: dua PR yang menambah izin pada peran berbeda, atau pada
        // tempat berbeda di peran yang sama, tidak menyentuh baris yang sama.
        return match ($this) {
            self::Pembeli => [
                NamaIzin::KeranjangKelola,
                NamaIzin::TokoBuka,
                NamaIzin::PesananBuat,
            ],
            self::Penjual => [
                NamaIzin::ProdukKelola,
                NamaIzin::PesananProses,
                NamaIzin::DompetLihat,
            ],
            self::Admin => [
                NamaIzin::TokoModerasi,
                NamaIzin::PeranKelola,
                NamaIzin::AkunTangguhkan,
            ],
            self::Keuangan => [
                NamaIzin::BukuBesarLihat,
            ],
            self::Auditor => [
                NamaIzin::BukuBesarLihat,
            ],
        };
    }
}
