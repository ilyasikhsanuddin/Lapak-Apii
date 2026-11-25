<?php

declare(strict_types=1);

namespace Tests\Pendukung;

use App\Domain\Pesanan\StatusPesanan;
use App\Models\ButirPesanan;
use App\Models\Checkout;
use App\Models\Pesanan;
use App\Models\Produk;

final class PesananUji
{
    public static function belumDibayar(): array
    {
        $checkout = Checkout::factory()->create();

        $pesanan = Pesanan::factory()
            ->for($checkout)
            ->create([
                'subtotal_rupiah' => 370_000,
                'ongkir_rupiah' => 12_000,
                'total_rupiah' => 382_000,
            ]);

        $produk = Produk::factory()->create(['stok' => 10]);

        ButirPesanan::factory()->for($pesanan)->create([
            'produk_id' => $produk->id,
            'harga_rupiah' => 185_000,
            'kuantitas' => 2,
            'subtotal_rupiah' => 370_000,
        ]);

        return compact('checkout', 'pesanan', 'produk');
    }

    public static function dibayar(): array
    {
        $data = self::belumDibayar();

        $data['pesanan']->forceFill([
            'status' => StatusPesanan::Dibayar,
            'dibayar_pada' => now(),
        ])->save();

        return $data;
    }
}
