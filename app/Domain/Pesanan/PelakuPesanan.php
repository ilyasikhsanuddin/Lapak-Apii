<?php

declare(strict_types=1);

namespace App\Domain\Pesanan;

/**
 * Kedudukan seseorang TERHADAP SATU PESANAN. Ini bukan peran RBAC.
 *
 * Peran menjawab "tindakan jenis apa yang boleh ia lakukan di Lapak".
 * Kedudukan menjawab "pada pesanan yang ini, ia siapa".
 *
 * Admin, keuangan, dan auditor sengaja tidak ada di sini:
 * mereka bukan pihak dalam jual beli.
 */
enum PelakuPesanan: string
{
    case Pembeli = 'pembeli';
    case Penjual = 'penjual';

    /** Konfirmasi pembayaran dan penjadwal. Tidak pernah berasal dari permintaan pengguna. */
    case Sistem = 'sistem';
}
