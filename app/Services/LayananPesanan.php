<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Enums\PeristiwaAudit;
use App\Exceptions\GalatDomain;
use App\Models\Checkout;
use App\Models\Pengguna;
use App\Models\Pesanan;
use Illuminate\Support\Collection;

final class LayananPesanan
{
    public function __construct(
        private readonly GembokCheckout $gembok,
        private readonly MesinStatusPesanan $mesin,
        private readonly PenjagaStok $stok,
        private readonly LayananDana $dana,
        private readonly LayananPembayaran $pembayaran,
        private readonly PencatatAudit $audit,
    ) {}

    public function kemas(Pesanan $pesanan): Pesanan
    {
        return $this->gembok->dalam(
            $pesanan->checkout_id,
            fn (Checkout $checkout): Pesanan => $this->mesin->pindahkan(
                $pesanan,
                StatusPesanan::Dikemas,
                PelakuPesanan::Penjual,
            ),
        );
    }

    public function kirim(Pesanan $pesanan, string $nomorResi): Pesanan
    {
        return $this->gembok->dalam(
            $pesanan->checkout_id,
            function (Checkout $checkout) use ($pesanan, $nomorResi): Pesanan {
                $terkini = $this->mesin->pindahkan(
                    $pesanan,
                    StatusPesanan::Dikirim,
                    PelakuPesanan::Penjual,
                );

                $terkini->forceFill([
                    'nomor_resi' => $nomorResi,
                ])->save();

                return $terkini;
            },
        );
    }

    public function terima(
        Pesanan $pesanan,
        PelakuPesanan $oleh,
        ?Pengguna $pelaku = null,
    ): Pesanan {
        return $this->gembok->dalam(
            $pesanan->checkout_id,
            function (Checkout $checkout) use ($pesanan, $oleh, $pelaku): Pesanan {
                $selesai = $this->mesin->pindahkan(
                    $pesanan,
                    StatusPesanan::Selesai,
                    $oleh,
                );

                $this->dana->lepaskan($selesai);

                $this->audit->catat(
                    PeristiwaAudit::DanaDilepas,
                    $pelaku,
                    'pesanan:'.$selesai->nomor,
                    [
                        'oleh' => $oleh->value,
                        'bagian_penjual_rupiah' => $this->dana->bagianPenjual($selesai)->rupiah,
                        'komisi_rupiah' => $this->dana->komisi($selesai)->rupiah,
                    ],
                );

                return $selesai;
            },
        );
    }

    public function batalkan(
        Pesanan $pesanan,
        PelakuPesanan $oleh,
        ?Pengguna $pelaku,
        string $alasan,
    ): Pesanan {
        return $this->gembok->dalam(
            $pesanan->checkout_id,
            function (Checkout $checkout) use ($pesanan, $oleh, $pelaku, $alasan): Pesanan {
                $terkini = Pesanan::query()
                    ->with(['butir', 'toko.pemilik'])
                    ->lockForUpdate()
                    ->findOrFail($pesanan->id);

                if ($terkini->status === StatusPesanan::MenungguPembayaran) {
                    throw new GalatDomain(
                        'LP-10',
                        'Pesanan yang belum dibayar dibatalkan bersama checkout-nya.',
                        409,
                    );
                }

                $dibatalkan = $this->mesin->pindahkan(
                    $terkini,
                    StatusPesanan::Dibatalkan,
                    $oleh,
                    $alasan,
                );

                $this->kembalikanStok(collect([$terkini]));
                $this->dana->kembalikan($dibatalkan);

                $this->audit->catat(
                    PeristiwaAudit::PesananDibatalkan,
                    $pelaku,
                    'pesanan:'.$dibatalkan->nomor,
                    [
                        'oleh' => $oleh->value,
                        'alasan' => $alasan,
                    ],
                );

                $this->audit->catat(
                    PeristiwaAudit::DanaDikembalikan,
                    $pelaku,
                    'pesanan:'.$dibatalkan->nomor,
                    [
                        'jumlah_rupiah' => $dibatalkan->total_rupiah,
                    ],
                );

                return $dibatalkan;
            },
        );
    }

    public function batalkanCheckout(
        int $idCheckout,
        PelakuPesanan $oleh,
        ?Pengguna $pelaku,
        string $alasan,
    ): bool {
        return $this->gembok->dalam(
            $idCheckout,
            function (Checkout $checkout) use ($oleh, $pelaku, $alasan): bool {
                $berhasil = $oleh === PelakuPesanan::Sistem
                    ? $this->pembayaran->tandaiKedaluwarsa($checkout)
                    : $this->pembayaran->tandaiDibatalkan($checkout);

                if (! $berhasil) {
                    return false;
                }

                $pesanan = Pesanan::query()
                    ->with('butir')
                    ->where('checkout_id', $checkout->id)
                    ->orderBy('id')
                    ->get();

                foreach ($pesanan as $satuPesanan) {
                    $this->mesin->pindahkan(
                        $satuPesanan,
                        StatusPesanan::Dibatalkan,
                        $oleh,
                        $alasan,
                    );
                }

                $this->kembalikanStok($pesanan);

                $this->audit->catat(
                    PeristiwaAudit::CheckoutDibatalkan,
                    $pelaku,
                    'checkout:'.$checkout->kode,
                    [
                        'oleh' => $oleh->value,
                        'alasan' => $alasan,
                        'jumlah_pesanan' => $pesanan->count(),
                    ],
                );

                return true;
            },
        );
    }

    /**
     * @param  Collection<int, Pesanan>  $pesanan
     */
    private function kembalikanStok(Collection $pesanan): void
    {
        $kembali = [];

        foreach ($pesanan as $satuPesanan) {
            foreach ($satuPesanan->butir as $butir) {
                $kembali[$butir->produk_id] = ($kembali[$butir->produk_id] ?? 0)
                    + $butir->kuantitas;
            }
        }

        ksort($kembali);

        foreach ($kembali as $idProduk => $kuantitas) {
            $this->stok->kembalikan(
                (int) $idProduk,
                $kuantitas,
            );
        }
    }
}
