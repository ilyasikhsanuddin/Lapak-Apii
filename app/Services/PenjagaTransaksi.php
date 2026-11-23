<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Uang;
use App\Exceptions\SesiTidakSegar;
use App\Models\Sesi;

/**
 * LP-15 untuk belanja: transaksi bernilai besar menuntut sesi segar.
 * Dipanggil checkout di dalam transaksinya, setelah total dihitung dan sebelum commit.
 */
final class PenjagaTransaksi
{
    public function pastikanLayak(Sesi $sesi, Uang $total): void
    {
        $batas = Uang::dari(config('lapak.transaksi.batas_segar_rupiah'));

        if ($total->lebihDari($batas) && ! $sesi->segar()) {
            throw new SesiTidakSegar;
        }
    }
}
