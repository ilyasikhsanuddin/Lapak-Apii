<?php

declare(strict_types=1);

namespace App\Exceptions;

final class PenyesuaianStokDitolak extends GalatDomain
{
    public static function akanNegatif(int $idProduk, string $nama, int $stokSekarang, int $selisih): self
    {
        return new self('LP-4', "Stok {$nama} tidak mencukupi untuk dikurangi sebesar ".abs($selisih).'.', 409, [
            'produk_id' => $idProduk,
            'stok_sekarang' => $stokSekarang,
            'selisih' => $selisih,
        ]);
    }

    public static function melewatiBatas(int $idProduk, string $nama, int $stokSekarang, int $selisih, int $maksimum): self
    {
        return new self('LP-4', "Stok {$nama} tidak boleh melebihi {$maksimum}.", 409, [
            'produk_id' => $idProduk,
            'stok_sekarang' => $stokSekarang,
            'selisih' => $selisih,
            'maksimum' => $maksimum,
        ]);
    }
}
