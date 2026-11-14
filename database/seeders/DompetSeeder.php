<?php

namespace Database\Seeders;

use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Models\MutasiSaldo;
use App\Models\Pengguna;
use App\Services\BukuBesar;
use Illuminate\Database\Seeder;
use RuntimeException;

class DompetSeeder extends Seeder
{
    private const PENJUAL = [
        'sri@lapak.test',
        'wulan@lapak.test',
        'eko@lapak.test',
        'tini@lapak.test',
    ];

    private const RUJUKAN = [
        'SEED-LAPAK-001-DANA',
        'SEED-LAPAK-001-KOMISI',
        'SEED-LAPAK-001-LEPAS',
    ];

    public function run(BukuBesar $buku): void
    {
        foreach ([
            JenisDompet::Eksternal,
            JenisDompet::Escrow,
            JenisDompet::Pendapatan,
        ] as $jenis) {
            $buku->dompetSistem($jenis);
        }

        $penjual = Pengguna::query()
            ->whereIn('surel', self::PENJUAL)
            ->get();

        if ($penjual->count() !== count(self::PENJUAL)) {
            throw new RuntimeException(
                'Pengguna penjual contoh belum lengkap. Jalankan PenggunaSeeder dan KatalogSeeder terlebih dahulu.'
            );
        }

        foreach ($penjual as $pengguna) {
            $buku->dompetPenjual($pengguna);
        }

        $jumlahMutasi = MutasiSaldo::query()
            ->whereIn('rujukan', self::RUJUKAN)
            ->count();

        if ($jumlahMutasi === 6) {
            return;
        }

        if ($jumlahMutasi !== 0) {
            throw new RuntimeException(
                'Mutasi contoh tidak lengkap. Periksa data mutasi sebelum menjalankan ulang seeder.'
            );
        }

        $eksternal = $buku->dompetSistem(JenisDompet::Eksternal);
        $escrow = $buku->dompetSistem(JenisDompet::Escrow);
        $pendapatan = $buku->dompetSistem(JenisDompet::Pendapatan);
        $dompetPenjual = $buku->dompetPenjual(
            $penjual->firstWhere('surel', 'sri@lapak.test')
        );

        $buku->pindahkan(
            $eksternal,
            $escrow,
            Uang::dari(500000),
            JenisMutasi::DanaMasuk,
            self::RUJUKAN[0],
            'Contoh pembayaran pesanan'
        );

        $buku->pindahkan(
            $escrow,
            $pendapatan,
            Uang::dari(25000),
            JenisMutasi::Komisi,
            self::RUJUKAN[1],
            'Contoh komisi Lapak'
        );

        $buku->pindahkan(
            $escrow,
            $dompetPenjual,
            Uang::dari(475000),
            JenisMutasi::LepasDana,
            self::RUJUKAN[2],
            'Contoh pencairan hasil penjualan'
        );
    }
}
