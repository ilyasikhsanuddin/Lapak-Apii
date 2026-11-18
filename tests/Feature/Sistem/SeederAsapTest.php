<?php

use App\Models\Dompet;
use App\Models\MutasiSaldo;
use App\Models\Pengguna;
use Database\Seeders\DatabaseSeeder;

it('menyiapkan dompet penjual dan enam mutasi contoh secara seimbang', function () {
    $this->seed(DatabaseSeeder::class);

    $penjual = Pengguna::query()
        ->whereIn('surel', [
            'sri@lapak.test',
            'wulan@lapak.test',
            'eko@lapak.test',
            'tini@lapak.test',
        ])
        ->get();

    expect($penjual)->toHaveCount(4);

    foreach ($penjual as $pengguna) {
        expect(
            Dompet::query()
                ->where('pengguna_id', $pengguna->id)
                ->where('jenis', 'penjual')
                ->exists()
        )->toBeTrue();
    }

    $mutasi = MutasiSaldo::query()
        ->whereIn('rujukan', [
            'SEED-LAPAK-001-DANA',
            'SEED-LAPAK-001-KOMISI',
            'SEED-LAPAK-001-LEPAS',
        ])
        ->get();

    expect($mutasi)->toHaveCount(6);

    $kelompok = $mutasi->groupBy('kelompok');

    expect($kelompok)->toHaveCount(3);

    foreach ($kelompok as $baris) {
        expect($baris)->toHaveCount(2);
        expect($baris->sum('jumlah_rupiah'))->toBe(0);
    }

    expect($mutasi->sum('jumlah_rupiah'))->toBe(0);
});

it('tidak menggandakan mutasi ketika seeder dijalankan kembali', function () {
    $this->seed(DatabaseSeeder::class);

    $jumlahAwal = MutasiSaldo::query()->count();

    $this->seed(DatabaseSeeder::class);

    expect(MutasiSaldo::query()->count())->toBe($jumlahAwal);
});
