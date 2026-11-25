<?php

namespace Database\Factories;

use App\Enums\StatusPencairan;
use App\Models\Pencairan;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pencairan>
 */
class PencairanFactory extends Factory
{
    protected $model = Pencairan::class;

    public function definition(): array
    {
        return [
            'kode' => 'CAIR-'.Str::ulid(),
            'pengaju_id' => Pengguna::factory()->penjual(),
            'jumlah_rupiah' => 50_000,
            'rekening_tujuan' => 'BCA 1234567890 a.n. Penjual',
            'status' => StatusPencairan::Menunggu,
            'diputuskan_oleh' => null,
            'diputuskan_pada' => null,
            'catatan' => null,
        ];
    }
}
