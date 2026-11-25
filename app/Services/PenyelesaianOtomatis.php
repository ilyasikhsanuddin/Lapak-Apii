<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Exceptions\TransisiPesananTidakSah;
use App\Models\Pesanan;

final class PenyelesaianOtomatis
{
    public function __construct(
        private readonly LayananPesanan $pesanan,
    ) {}

    public function jalankan(): int
    {
        $batas = now()->subDays(config('lapak.tahan_dana_hari'));
        $selesai = 0;

        $kandidat = Pesanan::query()
            ->where('status', StatusPesanan::Dikirim)
            ->where('dikirim_pada', '<=', $batas)
            ->orderBy('id')
            ->get();

        foreach ($kandidat as $pesanan) {
            $selesai += $this->selesaikan($pesanan) ? 1 : 0;
        }

        return $selesai;
    }

    public function selesaikan(Pesanan $pesanan): bool
    {
        try {
            $this->pesanan->terima($pesanan, PelakuPesanan::Sistem);
        } catch (TransisiPesananTidakSah) {
            return false;
        }

        return true;
    }
}
