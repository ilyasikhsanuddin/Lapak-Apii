<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Pesanan\PelakuPesanan;
use App\Models\Pembayaran;

final class PembatalanOtomatis
{
    private const ALASAN = 'Tidak dibayar sampai batas waktu';

    public function __construct(
        private readonly LayananPesanan $pesanan,
    ) {}

    /**
     * Membatalkan checkout yang tagihannya sudah melewati batas pembayaran.
     *
     * @return int Jumlah checkout yang berhasil dibatalkan.
     */
    public function jalankan(): int
    {
        $dibatalkan = 0;

        foreach (
            Pembayaran::query()
                ->lewatBatas()
                ->orderBy('id')
                ->pluck('checkout_id') as $id
        ) {
            $dibatalkan += $this->batalkan((int) $id) ? 1 : 0;
        }

        return $dibatalkan;
    }

    public function batalkan(int $idCheckout): bool
    {
        return $this->pesanan->batalkanCheckout(
            $idCheckout,
            PelakuPesanan::Sistem,
            null,
            self::ALASAN,
        );
    }
}
