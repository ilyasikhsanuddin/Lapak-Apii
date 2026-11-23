<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Belanja\PenghitungBelanja;
use App\Domain\Belanja\RincianToko;
use App\Domain\Pesanan\StatusPesanan;
use App\Exceptions\GalatDomain;
use App\Models\Alamat;
use App\Models\ButirPesanan;
use App\Models\Checkout;
use App\Models\Keranjang;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Sesi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LayananCheckout
{
    public function __construct(
        private readonly PenjagaStok $penjagaStok,
        private readonly PenyusunBelanja $penyusunBelanja,
        private readonly PenjagaTransaksi $penjagaTransaksi,
        private readonly LayananPembayaran $layananPembayaran,
    ) {}

    public function proses(
        Pengguna $pembeli,
        Alamat $alamat,
        Sesi $sesi,
    ): Checkout {
        return DB::transaction(function () use ($pembeli, $alamat, $sesi): Checkout {
            $keranjang = Keranjang::query()
                ->where('pengguna_id', $pembeli->id)
                ->lockForUpdate()
                ->first();

            if ($keranjang === null) {
                throw new GalatDomain(
                    'KERANJANG_KOSONG',
                    'Keranjang Anda kosong.',
                    422,
                );
            }

            $butirKeranjang = $keranjang->butir()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($butirKeranjang->isEmpty()) {
                throw new GalatDomain(
                    'KERANJANG_KOSONG',
                    'Keranjang Anda kosong.',
                    422,
                );
            }

            $diminta = $butirKeranjang->map(fn ($butir): array => [
                'produk_id' => (int) $butir->produk_id,
                'kuantitas' => (int) $butir->kuantitas,
                'catatan' => $butir->catatan,
            ])->all();

            $produk = $this->penjagaStok->ambil(
                array_column($diminta, 'produk_id'),
            );

            $rincian = $this->penyusunBelanja->susun(
                $diminta,
                $pembeli,
                $produk,
            );

            $total = PenghitungBelanja::totalBayar($rincian);

            $this->penjagaTransaksi->pastikanLayak($sesi, $total);

            $checkout = new Checkout;
            $checkout->forceFill([
                'kode' => (string) Str::ulid(),
                'pembeli_id' => $pembeli->id,
                'total_rupiah' => $total->rupiah,
                'pengenal_sesi' => $sesi->pengenal,
            ])->save();

            foreach ($rincian as $rincianToko) {
                $this->buatPesanan(
                    $checkout,
                    $rincianToko,
                    $alamat,
                    $produk,
                );
            }

            $this->layananPembayaran->buat($checkout);

            $keranjang->butir()->delete();

            return $checkout;
        });
    }

    /**
     * @param  Collection<int, Produk>  $produk
     */
    private function buatPesanan(
        Checkout $checkout,
        RincianToko $rincian,
        Alamat $alamat,
        Collection $produk,
    ): Pesanan {
        $pesanan = new Pesanan;
        $pesanan->forceFill([
            'checkout_id' => $checkout->id,
            'pembeli_id' => $checkout->pembeli_id,
            'toko_id' => $rincian->tokoId,
            'status' => StatusPesanan::MenungguPembayaran,
            'subtotal_rupiah' => $rincian->subtotal->rupiah,
            'ongkir_rupiah' => $rincian->ongkir->rupiah,
            'total_rupiah' => $rincian->total()->rupiah,
            'alamat_kirim' => $alamat->potret(),
        ])->save();

        foreach ($rincian->baris as $baris) {
            $butirPesanan = new ButirPesanan;
            $butirPesanan->forceFill([
                'pesanan_id' => $pesanan->id,
                'produk_id' => $baris->produkId,
                'nama_produk' => $baris->namaProduk,
                'harga_rupiah' => $baris->hargaSatuan->rupiah,
                'kuantitas' => $baris->kuantitas,
                'subtotal_rupiah' => $baris->subtotal()->rupiah,
            ])->save();

            $barang = $produk->get($baris->produkId);

            if ($barang === null) {
                throw new GalatDomain(
                    'PRODUK_TIDAK_DITEMUKAN',
                    'Produk tidak ditemukan.',
                    422,
                );
            }

            $this->penjagaStok->kurangi($barang, $baris->kuantitas);
        }

        return $pesanan;
    }
}
