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
use App\Models\Produk;
use App\Services\BukuBesar;
use App\Services\LayananPesanan;
use Tests\Pendukung\PesananUji;

final class BatalLawanKirim implements SkenarioBalapan
{
    public function keterangan(): string
    {
        return 'Pembeli membatalkan pesanan bersamaan dengan penjual mengirim pesanan';
    }

    public function proses(): int
    {
        return 8;
    }

    public function siapkan(): array
    {
        $data = PesananUji::dibayar();
        $pesanan = $data['pesanan'];
        $produk = $data['produk'];

        app(LayananPesanan::class)->kemas($pesanan);

        $produk->forceFill([
            'stok' => max(0, $produk->stok - 2),
        ])->save();

        $buku = app(BukuBesar::class);
        $escrow = $buku->dompetSistem(JenisDompet::Escrow);

        $saldoAwalEscrow = $escrow->saldo_rupiah;

        $buku->pindahkan(
            $buku->dompetSistem(JenisDompet::Eksternal),
            $escrow,
            Uang::dari($pesanan->total_rupiah),
            JenisMutasi::DanaMasuk,
            $pesanan->nomor,
            'Dana awal skenario pembatalan',
        );

        return [
            'pesanan' => $pesanan->id,
            'nomor' => $pesanan->nomor,
            'produk' => $produk->id,
            'stok_awal' => $produk->stok,
            'saldo_awal_escrow' => $saldoAwalEscrow,
        ];
    }

    public function jalankan(array $konteks, int $nomor): string
    {
        $pesanan = Pesanan::query()->findOrFail($konteks['pesanan']);

        if ($nomor % 2 === 0) {
            try {
                app(LayananPesanan::class)->kirim(
                    $pesanan,
                    'RESI-BALAPAN-'.$nomor,
                );

                return 'penjual: terkirim';
            } catch (TransisiPesananTidakSah) {
                return 'penjual: kalah';
            }
        }

        try {
            app(LayananPesanan::class)->batalkan(
                $pesanan,
                PelakuPesanan::Pembeli,
                $pesanan->checkout->pembeli,
                'Pembeli membatalkan pesanan uji',
            );

            return 'pembeli: dibatalkan';
        } catch (TransisiPesananTidakSah) {
            return 'pembeli: kalah';
        }
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $pesanan = Pesanan::query()->findOrFail($konteks['pesanan'])->refresh();
        $produk = Produk::query()->findOrFail($konteks['produk']);

        $escrow = app(BukuBesar::class)
            ->dompetSistem(JenisDompet::Escrow)
            ->refresh();

        $pengembalian = MutasiSaldo::query()
            ->where('rujukan', $pesanan->nomor)
            ->where('jenis', JenisMutasi::Pengembalian)
            ->count();

        $auditBatal = LogAudit::query()
            ->where('peristiwa', PeristiwaAudit::PesananDibatalkan->value)
            ->where('sasaran', 'pesanan:'.$pesanan->nomor)
            ->count();

        $auditDana = LogAudit::query()
            ->where('peristiwa', PeristiwaAudit::DanaDikembalikan->value)
            ->where('sasaran', 'pesanan:'.$pesanan->nomor)
            ->count();

        $pelanggaran = [];

        if ($pesanan->status === StatusPesanan::Dibatalkan) {
            if ($produk->stok !== $konteks['stok_awal'] + 2) {
                $pelanggaran[] = 'Pembatalan menang, tetapi stok tidak dikembalikan.';
            }

            if ($escrow->saldo_rupiah !== $konteks['saldo_awal_escrow']) {
                $pelanggaran[] = 'Saldo escrow tidak kembali ke saldo awal.';
            }

            if ($pengembalian !== 2) {
                $pelanggaran[] = "Mutasi pengembalian {$pengembalian}, seharusnya 2.";
            }

            if ($auditBatal !== 1 || $auditDana !== 1) {
                $pelanggaran[] = 'Audit pembatalan atau pengembalian tidak tepat satu kali.';
            }

            if ($pesanan->nomor_resi !== null) {
                $pelanggaran[] = 'Nomor resi terisi meskipun pembatalan menang.';
            }
        } elseif ($pesanan->status === StatusPesanan::Dikirim) {
            if ($produk->stok !== $konteks['stok_awal']) {
                $pelanggaran[] = 'Pengiriman menang, tetapi stok berubah.';
            }

            if ($escrow->saldo_rupiah !== $konteks['saldo_awal_escrow'] + $pesanan->total_rupiah) {
                $pelanggaran[] = 'Dana berubah meskipun pengiriman menang.';
            }

            if ($pengembalian !== 0) {
                $pelanggaran[] = 'Ada pengembalian dana meskipun pengiriman menang.';
            }

            if ($pesanan->nomor_resi === null) {
                $pelanggaran[] = 'Pengiriman menang tetapi nomor resi kosong.';
            }

            if ($auditBatal !== 0 || $auditDana !== 0) {
                $pelanggaran[] = 'Audit pembatalan tercatat meskipun pengiriman menang.';
            }
        } else {
            $pelanggaran[] = 'Status akhir bukan Dibatalkan atau Dikirim.';
        }

        return $pelanggaran;
    }
}
