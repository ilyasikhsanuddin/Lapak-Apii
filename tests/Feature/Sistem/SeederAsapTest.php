<?php

use App\Enums\JenisDompet;
use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\MutasiSaldo;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;
use Database\Seeders\DatabaseSeeder;

/*
 * Uji asap seeder (Latihan Mandiri 2, D5).
 *
 * Menjalankan DatabaseSeeder dari basis data kosong, lalu memastikan jumlah baris.
 * Angka di bawah mengikuti seeder pada modul:
 *   - PenggunaSeeder : 9 akun contoh
 *   - KatalogSeeder  : 4 kategori, 4 toko, 13 produk (4 + 4 + 3 + 2)
 *   - DompetSeeder   : 3 dompet sistem (eksternal, escrow, pendapatan)
 *                      + 4 dompet penjual = 7 dompet
 *   - Mutasi contoh  : 6 baris (3 kelompok x 2 baris, seimbang)
 *
 * Bila seseorang mengubah data seeder dengan sengaja, ubah angka di sini pada PR yang sama.
 * Bila satu baris $this->call() di DatabaseSeeder hilang (skenario Langkah 18), uji ini merah.
 */

it('menjalankan seluruh seeder dengan jumlah baris yang sesuai', function () {
    $this->seed();

    expect(Pengguna::count())->toBe(9)
        ->and(Kategori::count())->toBe(4)
        ->and(Toko::count())->toBe(4)
        ->and(Produk::count())->toBe(13)
        ->and(Dompet::count())->toBe(7);
});

it('membuat tiga dompet sistem dan satu dompet per penjual', function () {
    $this->seed();

    expect(Dompet::where('jenis', JenisDompet::Eksternal)->count())->toBe(1)
        ->and(Dompet::where('jenis', JenisDompet::Escrow)->count())->toBe(1)
        ->and(Dompet::where('jenis', JenisDompet::Pendapatan)->count())->toBe(1)
        ->and(Dompet::where('jenis', JenisDompet::Penjual)->count())->toBe(4);
});

it('idempoten: seeder dijalankan dua kali tidak menggandakan baris', function () {
    $this->seed();
    $this->seed();

    expect(Pengguna::count())->toBe(9)
        ->and(Toko::count())->toBe(4)
        ->and(Produk::count())->toBe(13)
        ->and(Dompet::count())->toBe(7);
});

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