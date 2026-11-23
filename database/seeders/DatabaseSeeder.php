<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $seeders = [
            PeranIzinSeeder::class,
            PenggunaSeeder::class,
            AlamatSeeder::class,
            KatalogSeeder::class,
            DompetSeeder::class,
            ContohMutasiSeeder::class,
        ];

        foreach ($seeders as $seeder) {
            if (class_exists($seeder)) {
                $this->call($seeder);
            }
        }
    }
}
