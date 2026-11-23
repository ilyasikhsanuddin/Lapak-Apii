<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Models\Pembayaran;
use App\Models\Pesanan;

final class PembatalanOtomatis
{
    public function __construct(
        private readonly GembokCheckout $gembokCheckout,
        private readonly LayananPembayaran $layananPembayaran,
        private readonly MesinStatusPesanan $mesinStatusPesanan,
        private readonly PenjagaStok $penjagaStok,
    ) {}

    /**
     * Membatalkan checkout yang tagihannya sudah melewati batas pembayaran.
     *
     * @return int Jumlah checkout yang berhasil dibatalkan.
     */
    public function jalankan(): int
    {
        $idCheckout = Pembayaran::query()
            ->lewatBatas()
            ->orderBy('checkout_id')
            ->pluck('checkout_id');

        $jumlahDibatalkan = 0;

        foreach ($idCheckout as $id) {
            $berhasil = $this->gembokCheckout->dalam(
                (int) $id,
                function ($checkout): bool {
                    if (! $this->layananPembayaran->tandaiKedaluwarsa($checkout)) {
                        return false;
                    }

                    $pesanan = Pesanan::query()
                        ->where('checkout_id', $checkout->id)
                        ->with('butir')
                        ->orderBy('id')
                        ->get();

                    $kembali = [];

                    foreach ($pesanan as $satuPesanan) {
                        $this->mesinStatusPesanan->pindahkan(
                            $satuPesanan,
                            StatusPesanan::Dibatalkan,
                            PelakuPesanan::Sistem,
                            'Tidak dibayar dalam batas waktu pembayaran.',
                        );

                        foreach ($satuPesanan->butir as $butir) {
                            $kembali[$butir->produk_id] = ($kembali[$butir->produk_id] ?? 0)
                                + $butir->kuantitas;
                        }
                    }

                    // Kembalikan stok berdasarkan urutan ID produk menaik.
                    ksort($kembali);

                    foreach ($kembali as $idProduk => $kuantitas) {
                        $this->penjagaStok->kembalikan(
                            $idProduk,
                            $kuantitas,
                        );
                    }

                    return true;
                },
            );

            if ($berhasil) {
                $jumlahDibatalkan++;
            }
        }

        return $jumlahDibatalkan;
    }
}
