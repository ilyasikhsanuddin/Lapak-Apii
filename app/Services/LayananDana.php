<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Models\MutasiSaldo;
use App\Models\Pesanan;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Jalan keluar dana dari escrow. Ada tepat dua: dilepas kepada penjual, atau dikembalikan
 * kepada pembeli. Keduanya dipanggil LayananPesanan di dalam GembokCheckout, sesaat setelah
 * status pesanan berubah, sehingga status dan uangnya tidak pernah berbeda cerita.
 */
final class LayananDana
{
    public function __construct(private readonly BukuBesar $buku) {}

    /** LP-8: komisi dihitung dari subtotal produk, dengan pembulatan milik Uang::persen(). */
    public function komisi(Pesanan $pesanan): Uang
    {
        return Uang::dari($pesanan->subtotal_rupiah)->persen(config('lapak.komisi_persen'));
    }

    /** Yang diterima penjual: subtotal dikurangi komisi, ditambah ongkir yang utuh miliknya. */
    public function bagianPenjual(Pesanan $pesanan): Uang
    {
        return Uang::dari($pesanan->total_rupiah)->kurang($this->komisi($pesanan));
    }

    /** LP-12: pesanan selesai, dana keluar dari escrow menjadi saldo penjual dan komisi. */
    public function lepaskan(Pesanan $pesanan): void
    {
        $this->pastikanBelumKeluar($pesanan);

        $escrow = $this->buku->dompetSistem(JenisDompet::Escrow);
        $pendapatan = $this->buku->dompetSistem(JenisDompet::Pendapatan);
        $penjual = $this->buku->dompetPenjual($pesanan->toko->pemilik);
        $persen = config('lapak.komisi_persen');

        // Dompet sistem bernomor lebih kecil daripada dompet penjual mana pun, maka komisi
        // dipindahkan lebih dulu: kunci dompet tetap diambil urut nomor menaik.
        $this->buku->pindahkan(
            $escrow, $pendapatan, $this->komisi($pesanan),
            JenisMutasi::Komisi, $pesanan->nomor, "Komisi {$persen}%",
        );
        $this->buku->pindahkan(
            $escrow, $penjual, $this->bagianPenjual($pesanan),
            JenisMutasi::LepasDana, $pesanan->nomor, 'Dana dilepas ke penjual',
        );
    }

    /** Pesanan yang sudah dibayar batal: seluruh nilainya kembali kepada pembeli. */
    public function kembalikan(Pesanan $pesanan): void
    {
        $this->pastikanBelumKeluar($pesanan);

        $this->buku->pindahkan(
            $this->buku->dompetSistem(JenisDompet::Escrow),
            $this->buku->dompetSistem(JenisDompet::Eksternal),
            Uang::dari($pesanan->total_rupiah),
            JenisMutasi::Pengembalian,
            $pesanan->nomor,
            'Pengembalian dana ke pembeli',
        );
    }

    /**
     * Sabuk pengaman kedua. Yang menahan balapan adalah mesin status di bawah gembok: sebuah
     * pesanan hanya dapat selesai atau batal satu kali. Pemeriksaan ini memakai pembacaan biasa
     * dan hanya dimaksudkan menangkap kesalahan pemrograman, yaitu pemanggil yang melewati
     * mesin status.
     */
    private function pastikanBelumKeluar(Pesanan $pesanan): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Dana hanya boleh dipindahkan di dalam GembokCheckout.');
        }

        $sudah = MutasiSaldo::query()
            ->where('rujukan', $pesanan->nomor)
            ->whereIn('jenis', [JenisMutasi::LepasDana, JenisMutasi::Pengembalian])
            ->exists();

        if ($sudah) {
            throw new LogicException("Dana {$pesanan->nomor} sudah pernah keluar dari escrow.");
        }
    }
}
