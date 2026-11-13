<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Belanja\AlasanBermasalah;
use App\Domain\Belanja\BarisBelanja;
use App\Domain\Belanja\ButirBermasalah;
use App\Domain\Belanja\PenghitungBelanja;
use App\Domain\Belanja\RincianToko;
use App\Domain\Uang;
use App\Exceptions\ProdukTidakDapatDibeli;
use App\Models\Produk;

final class PenyusunBelanja
{
    /**
     * Memilah butir menjadi pesanan yang valid dan butir bermasalah.
     *
     * @param  list<array{produk_id: int, kuantitas: int}>  $butir
     * @return array{list<RincianToko>, list<ButirBermasalah>}
     */
    public function pilah(array $butir): array
    {
        $diminta = collect($butir)->pluck('kuantitas', 'produk_id');

        $produk = Produk::query()
            ->tampil()
            ->with('toko')
            ->whereIn('id', $diminta->keys())
            ->get()
            ->keyBy('id');

        $hilang = $diminta->keys()->diff($produk->keys());

        $baris = [];
        $toko = [];
        $bermasalah = [];

        foreach ($diminta as $produkId => $kuantitas) {
            $barang = $produk->get($produkId);

            if ($barang === null) {
                $bermasalah[] = new ButirBermasalah(
                    (int) $produkId,
                    (int) $kuantitas,
                    AlasanBermasalah::TidakTersedia,
                );

                continue;
            }

            if ($barang->stok < $kuantitas) {
                $bermasalah[] = new ButirBermasalah(
                    $barang->id,
                    $kuantitas,
                    AlasanBermasalah::StokKurang,
                    $barang->nama,
                    $barang->stok,
                );

                continue;
            }

            $baris[] = new BarisBelanja(
                produkId: $barang->id,
                namaProduk: $barang->nama,
                tokoId: $barang->toko_id,
                hargaSatuan: Uang::dari($barang->harga_rupiah),
                kuantitas: $kuantitas,
            );

            $toko[$barang->toko_id] = [
                'nama' => $barang->toko->nama,
                'ongkir' => Uang::dari($barang->toko->ongkir_rupiah),
            ];
        }

        $rincian = $baris === []
            ? []
            : (new PenghitungBelanja(config('lapak.komisi_persen')))
                ->hitung($baris, $toko);

        return [$rincian, $bermasalah];
    }

    /**
     * Menyusun belanja dengan aturan ketat seperti sebelumnya.
     *
     * @param  list<array{produk_id: int, kuantitas: int}>  $butir
     * @return list<RincianToko>
     */
    public function susun(array $butir): array
    {
        [$rincian, $bermasalah] = $this->pilah($butir);

        $tidakTersedia = array_filter(
            $bermasalah,
            fn (ButirBermasalah $item): bool => $item->alasan === AlasanBermasalah::TidakTersedia,
        );

        if ($tidakTersedia !== []) {
            throw ProdukTidakDapatDibeli::tidakTampil(
                array_map(
                    fn (ButirBermasalah $item): int => $item->produkId,
                    array_values($tidakTersedia),
                ),
            );
        }

        foreach ($bermasalah as $item) {
            if ($item->alasan === AlasanBermasalah::StokKurang) {
                throw ProdukTidakDapatDibeli::stokKurang(
                    $item->produkId,
                    $item->namaProduk ?? 'Produk',
                    $item->kuantitas,
                    $item->stokTersedia ?? 0,
                );
            }
        }

        return $rincian;
    }
}
