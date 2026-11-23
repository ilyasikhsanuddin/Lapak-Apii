<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Alamat;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alamat>
 */
final class AlamatFactory extends Factory
{
    protected $model = Alamat::class;

    public function definition(): array
    {
        return [
            'pengguna_id' => Pengguna::factory(),
            'label' => 'Rumah',
            'nama_penerima' => fake()->name(),
            'telepon' => '0812'.fake()->numerify('########'),
            'jalan' => fake()->streetAddress(),
            'kota' => fake()->city(),
            'kode_pos' => fake()->numerify('#####'),
            'utama' => true,
        ];
    }

    public function cadangan(): static
    {
        return $this->state(['label' => 'Kantor', 'utama' => false]);
    }
}
