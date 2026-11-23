<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Models\Kategori;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\PenerbitSesi;
use Illuminate\Support\Str;

/**
 * Delapan permintaan POST /toko-saya/produk/{id}/stok datang bersamaan:
 * - empat permintaan pertama mengirim kunci idempotensi berbeda (+5 tiap kali = +20),
 * - empat permintaan sisanya mengulang kunci yang sama (harus diulang tanpa menambah stok).
 */
final class PenyesuaianStok implements SkenarioBalapan
{
    use PanggilHttp;

    private const STOK_AWAL = 10;

    public function keterangan(): string
    {
        return 'Penyesuaian stok serempak dengan kombinasi kunci idempotensi unik dan duplikat';
    }

    public function proses(): int
    {
        return 8;
    }

    public function siapkan(): array
    {
        $akhiran = Str::lower(Str::random(8));
        $penjual = Pengguna::factory()->penjual()->create();
        $toko = Toko::factory()->for($penjual, 'pemilik')->create(['slug' => "toko-stok-{$akhiran}"]);
        $produk = Produk::factory()->for($toko)->for(Kategori::query()->firstOrFail())->create([
            'slug' => "produk-stok-{$akhiran}",
            'stok' => self::STOK_AWAL,
        ]);
        [, $kunciSesi] = app(PenerbitSesi::class)->terbitkan($penjual, 'Balapan stok');

        return [
            'produk' => $produk->id,
            'sesi' => $kunciSesi,
            'awalan_kunci' => "balapan-stok-{$akhiran}",
        ];
    }

    public function jalankan(array $konteks, int $nomor): string
    {
        // Proses 1..4 pakai kunci unik; proses 5..8 mengulang kunci 1..4.
        $indeksKunci = (($nomor - 1) % 4) + 1;
        $kunciIdempoten = "{$konteks['awalan_kunci']}-{$indeksKunci}";

        [$status,$json, $header] = $this->panggil(
            'POST',
            "/api/v1/toko-saya/produk/{$konteks['produk']}/stok",
            ['selisih' => 5],
            [
                'Authorization' => "Bearer {$konteks['sesi']}",
                'Idempotency-Key' => $kunciIdempoten,
            ],
        );

        if (isset($header['idempotency-replayed'])) {
            return "{$status} replayed";
        }

        return $this->ringkas($status, $json);
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $stokAkhir = Produk::query()->findOrFail($konteks['produk'])->stok;
        $harapan = self::STOK_AWAL + (4 * 5);
        $pelanggaran = [];

        if ($stokAkhir !== $harapan) {
            $pelanggaran[] = "Stok akhir {$stokAkhir}, seharusnya {$harapan} (4 penyesuaian unik x +5).";
        }

        return $pelanggaran;
    }
}
