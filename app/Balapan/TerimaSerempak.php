<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Enums\PeristiwaAudit;
use App\Exceptions\TransisiPesananTidakSah;
use App\Models\LogAudit;
use App\Models\MutasiSaldo;
use App\Models\Pesanan;
use App\Services\BukuBesar;
use App\Services\LayananPesanan;
use App\Services\PenyelesaianOtomatis;
use Tests\Pendukung\PesananUji;

final class TerimaSerempak implements SkenarioBalapan
{
    public function keterangan(): string
    {
        return 'Pembeli menerima pesanan bersamaan dengan penyelesaian otomatis';
    }

    public function proses(): int
    {
        return 8;
    }

    public function siapkan(): array
    {
        $data = PesananUji::dibayar();
        $pesanan = $data['pesanan'];

        app(LayananPesanan::class)->kemas($pesanan);
        app(LayananPesanan::class)->kirim($pesanan, 'JNE-BALAPAN-TERIMA');

        $buku = app(BukuBesar::class);
        $escrow = $buku->dompetSistem(JenisDompet::Escrow);
        $pendapatan = $buku->dompetSistem(JenisDompet::Pendapatan);
        $penjual = $buku->dompetPenjual($pesanan->toko->pemilik);

        $saldoAwal = [
            'escrow' => $escrow->saldo_rupiah,
            'pendapatan' => $pendapatan->saldo_rupiah,
            'penjual' => $penjual->saldo_rupiah,
        ];

        $buku->pindahkan(
            $buku->dompetSistem(JenisDompet::Eksternal),
            $escrow,
            Uang::dari($pesanan->total_rupiah),
            JenisMutasi::DanaMasuk,
            $pesanan->nomor,
            'Dana awal skenario balapan',
        );

        return [
            'pesanan' => $pesanan->id,
            'nomor' => $pesanan->nomor,
            'saldo_awal' => $saldoAwal,
        ];
    }

    public function jalankan(array $konteks, int $nomor): string
    {
        $pesanan = Pesanan::query()->findOrFail($konteks['pesanan']);

        if ($nomor % 2 === 0) {
            $selesai = app(PenyelesaianOtomatis::class)->selesaikan($pesanan);

            return $selesai ? 'sistem: selesai' : 'sistem: dilewati';
        }

        try {
            app(LayananPesanan::class)->terima(
                $pesanan,
                PelakuPesanan::Pembeli,
                $pesanan->checkout->pembeli,
            );

            return 'pembeli: selesai';
        } catch (TransisiPesananTidakSah) {
            return 'pembeli: kalah';
        }
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $pesanan = Pesanan::query()->findOrFail($konteks['pesanan'])->refresh();
        $buku = app(BukuBesar::class);

        $escrow = $buku->dompetSistem(JenisDompet::Escrow)->refresh();
        $pendapatan = $buku->dompetSistem(JenisDompet::Pendapatan)->refresh();
        $penjual = $buku->dompetPenjual($pesanan->toko->pemilik)->refresh();

        $awal = $konteks['saldo_awal'];

        $mutasi = MutasiSaldo::query()
            ->where('rujukan', $pesanan->nomor)
            ->count();

        $audit = LogAudit::query()
            ->where('peristiwa', PeristiwaAudit::DanaDilepas->value)
            ->where('sasaran', 'pesanan:'.$pesanan->nomor)
            ->count();

        $pelanggaran = [];

        if ($pesanan->status !== StatusPesanan::Selesai) {
            $pelanggaran[] = 'Pesanan tidak berakhir pada status Selesai.';
        }

        if ($escrow->saldo_rupiah !== $awal['escrow']) {
            $pelanggaran[] = 'Saldo escrow tidak kembali ke saldo awal.';
        }

        if ($pendapatan->saldo_rupiah !== $awal['pendapatan'] + 18500) {
            $pelanggaran[] = 'Komisi tidak bertambah tepat Rp18.500.';
        }

        if ($penjual->saldo_rupiah !== $awal['penjual'] + 363500) {
            $pelanggaran[] = 'Saldo penjual tidak bertambah tepat Rp363.500.';
        }

        if ($mutasi !== 6) {
            $pelanggaran[] = "Jumlah mutasi {$mutasi}, seharusnya 6.";
        }

        if ($audit !== 1) {
            $pelanggaran[] = "Jumlah audit pelepasan dana {$audit}, seharusnya 1.";
        }

        return $pelanggaran;
    }
}
