<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Pesanan\AturanTransisi;
use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Exceptions\TransisiPesananTidakSah;
use App\Models\Pesanan;
use Illuminate\Support\Facades\DB;
use LogicException;

final class MesinStatusPesanan
{
    public function pindahkan(
        Pesanan $pesanan,
        StatusPesanan $ke,
        PelakuPesanan $oleh,
        ?string $alasanBatal = null,
    ): Pesanan {
        if (DB::transactionLevel() === 0) {
            throw new LogicException(
                'Status pesanan hanya boleh diubah di dalam GembokCheckout.',
            );
        }

        $terkini = Pesanan::query()
            ->lockForUpdate()
            ->findOrFail($pesanan->id);

        $dari = $terkini->status;

        if (! AturanTransisi::boleh($dari, $ke, $oleh)) {
            throw new TransisiPesananTidakSah($dari, $ke);
        }

        $perubahan = ['status' => $ke] + match ($ke) {
            StatusPesanan::Dibayar => [
                'dibayar_pada' => now(),
            ],
            StatusPesanan::Dikemas => [
                'dikemas_pada' => now(),
            ],
            StatusPesanan::Dikirim => [
                'dikirim_pada' => now(),
            ],
            StatusPesanan::Selesai => [
                'selesai_pada' => now(),
            ],
            StatusPesanan::Dibatalkan => [
                'dibatalkan_pada' => now(),
                'dibatalkan_oleh' => $oleh->value,
                'alasan_batal' => $alasanBatal,
            ],
        };

        $terkini->forceFill($perubahan)->save();

        return $terkini;
    }
}
