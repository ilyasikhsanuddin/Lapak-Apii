<?php

namespace Database\Factories;

use App\Models\ButirPesanan;
use App\Models\Pesanan;
use App\Models\Produk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ButirPesanan>
 */
class ButirPesananFactory extends Factory
{
    protected $model = ButirPesanan::class;

    public function definition(): array
    {
        $harga = 25_000;
        $kuantitas = 2;

        return [
            'pesanan_id' => Pesanan::factory(),
            'produk_id' => Produk::factory(),
            'nama_produk' => 'Produk Contoh',
            'harga_rupiah' => $harga,
            'kuantitas' => $kuantitas,
            'subtotal_rupiah' => $harga * $kuantitas,
        ];
    }
}
