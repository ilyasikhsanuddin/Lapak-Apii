<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Pengguna;
use App\Models\Toko;

final class TokoPolicy
{
    /**
     * Admin tidak memutuskan nasib tokonya sendiri. Satu akun boleh memegang peran admin sekaligus
     * penjual (LP-2), dan justru karena itu benturan kepentingan ini harus ditutup di sini.
     */
    public function moderasi(Pengguna $pelaku, Toko $toko): bool
    {
        return $toko->pemilik_id !== $pelaku->id;
    }
}
