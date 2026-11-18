<?php

namespace Database\Seeders;

use App\Enums\NamaIzin;
use App\Enums\NamaPeran;
use App\Models\Izin;
use App\Models\Peran;
use Illuminate\Database\Seeder;

/**
 * Menyalin daftar peran dan izin dari enum ke basis data.
 * Aman dijalankan berulang kali, termasuk di produksi setiap kali ada rilis:
 * izin yang dihapus dari NamaPeran::izinBawaan() ikut tercabut dari perannya.
 */
class PeranIzinSeeder extends Seeder
{
    public function run(): void
    {
        $izin = [];
        foreach (NamaIzin::cases() as $nama) {
            $izin[$nama->value] = Izin::updateOrCreate(
                ['nama' => $nama->value],
                ['keterangan' => $nama->keterangan()],
            )->id;
        }

        foreach (NamaPeran::cases() as $nama) {
            $peran = Peran::updateOrCreate(
                ['nama' => $nama->value],
                ['keterangan' => $nama->keterangan()],
            );

            // sync(): izin yang tidak lagi terdaftar ikut dilepas, bukan hanya yang baru ditambahkan.
            $peran->izin()->sync(array_map(fn (NamaIzin $i) => $izin[$i->value], $nama->izinBawaan()));
        }
    }
}
