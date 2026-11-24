<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Exceptions\ProdukTidakDapatDibeli;
use App\Models\ButirPesanan;
use App\Models\Checkout;
use App\Models\Kategori;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\PembatalanOtomatis;
use App\Services\PenjagaStok;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pembatalan otomatis bertemu pembelian atas dua produk yang sama.
 * Aturannya: tidak ada proses yang mati karena kebuntuan, dan stok akhir tepat.
 *
 * Butir pesanan sengaja disimpan dengan nomor produk besar lebih dulu. Bila pembatalan
 * mengembalikan stok menurut urutan butir, ia mengunci berlawanan arah dengan pembelian
 * (urut nomor), dan MySQL mematikan salah satunya.
 */
final class BatalSaatCheckout implements SkenarioBalapan
{
    // Harus >= jumlah pembeli (6), supaya tidak ada pembelian yang ditolak karena stok habis.
    private const STOK_AWAL = 10;

    public function __construct(
        private readonly PenjagaStok $penjaga,
        private readonly PembatalanOtomatis $pembatalan,
    ) {}

    public function keterangan(): string
    {
        return 'Pembatalan otomatis bertemu pembelian produk yang sama: tidak ada kebuntuan';
    }

    public function proses(): int
    {
        return 12;
    }

    public function siapkan(): array
    {
        $akhiran = Str::lower(Str::random(8));

        $toko = Toko::factory()->create(['slug' => "toko-{$akhiran}"]);
        $kategori = Kategori::query()->firstOrFail();

        [$kecil, $besar] = Produk::factory()->count(2)->for($toko)->for($kategori)
            ->sequence(['slug' => "produk-a-{$akhiran}"], ['slug' => "produk-b-{$akhiran}"])
            ->create(['stok' => self::STOK_AWAL])
            ->all();

        $checkout = [];

        foreach (range(1, intdiv($this->proses(), 2)) as $ke) {
            $induk = Checkout::factory()->create();
            $pesanan = Pesanan::factory()->for($induk)->for($toko)->create();

            // Nomor produk besar disimpan lebih dulu.
            ButirPesanan::factory()->for($pesanan)->create(['produk_id' => $besar->id, 'kuantitas' => 1]);
            ButirPesanan::factory()->for($pesanan)->create(['produk_id' => $kecil->id, 'kuantitas' => 1]);

            Pembayaran::factory()->for($induk)->create(['batas_bayar_pada' => now()->subMinute()]);

            $checkout[] = $induk->id;
        }

        return ['produk' => [$kecil->id, $besar->id], 'checkout' => $checkout];
    }

    public function jalankan(array $konteks, int $nomor): string
    {
        // Nomor genap: penjadwal menjalankan pembatalan otomatis (bisa membatalkan banyak checkout). Nomor ganjil: pembeli.
        if ($nomor % 2 === 0) {
            // jalankan() membatalkan semua checkout kedaluwarsa yang ia dapat dan mengembalikan jumlahnya.
            return 'penjadwal: batal '.$this->pembatalan->jalankan();
        }

        try {
            DB::transaction(function () use ($konteks): void {
                foreach ($this->penjaga->ambil($konteks['produk']) as $produk) {
                    $this->penjaga->kurangi($produk, 1);
                }
            });
        } catch (ProdukTidakDapatDibeli) {
            return 'pembeli: ditolak';
        }

        return 'pembeli: beli 2';
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $awalan = 'penjadwal: batal ';

        $prosesPenjadwal = count(array_filter($hasil, fn (string $h) => str_starts_with($h, $awalan)));
        $batal = array_sum(array_map(
            fn (string $h) => str_starts_with($h, $awalan) ? (int) substr($h, strlen($awalan)) : 0,
            $hasil,
        ));
        $beli = count(array_filter($hasil, fn (string $h) => $h === 'pembeli: beli 2'));
        $seharusnya = self::STOK_AWAL + $batal - $beli;

        $pelanggaran = [];

        if ($prosesPenjadwal + $beli !== count($hasil)) {
            $pelanggaran[] = 'Ada proses yang gagal, misalnya karena kebuntuan (deadlock).';
        }

        foreach (Produk::query()->findMany($konteks['produk']) as $produk) {
            if ($produk->stok !== $seharusnya) {
                $pelanggaran[] = "Stok produk {$produk->id} adalah {$produk->stok}, "
                    ."seharusnya {$seharusnya}.";
            }
        }

        return $pelanggaran;
    }
}
