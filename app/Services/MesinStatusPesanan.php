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

        $perubahan = ['status' => $ke];

        if ($ke === StatusPesanan::Dibayar) {
            $perubahan['dibayar_pada'] = now();
        }

        if ($ke === StatusPesanan::Dibatalkan) {
            $perubahan['dibatalkan_pada'] = now();
            $perubahan['alasan_batal'] = $alasanBatal;
        }

        $terkini->forceFill($perubahan)->save();

        return $terkini;
    }
}
