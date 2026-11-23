<?php

namespace Database\Factories;

use App\Enums\StatusPembayaran;
use App\Models\Checkout;
use App\Models\Pembayaran;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pembayaran>
 */
class PembayaranFactory extends Factory
{
    protected $model = Pembayaran::class;

    public function definition(): array
    {
        return [
            'checkout_id' => Checkout::factory(),
            'kode' => 'BYR-'.Str::ulid(),
            'jumlah_rupiah' => 197_000,
            'status' => StatusPembayaran::Menunggu,
            'batas_bayar_pada' => now()->addHours(24),
            'lunas_pada' => null,
            'id_peristiwa' => null,
        ];
    }
}
