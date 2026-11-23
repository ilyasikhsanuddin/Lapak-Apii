<?php

declare(strict_types=1);

namespace App\Domain\Pesanan;

/**
 * Menentukan kedudukan pengguna terhadap satu pesanan.
 *
 * Kedudukan ini berbeda dari peran RBAC pengguna.
 */
final class KedudukanPesanan
{
    public static function dari(
        int $idPengguna,
        int $idPembeli,
        int $idPemilikToko,
    ): ?PelakuPesanan {
        if ($idPengguna === $idPembeli) {
            return PelakuPesanan::Pembeli;
        }

        if ($idPengguna === $idPemilikToko) {
            return PelakuPesanan::Penjual;
        }

        return null;
    }
}
