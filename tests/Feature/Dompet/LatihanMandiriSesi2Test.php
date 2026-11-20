<?php

use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Models\MutasiSaldo;
use App\Models\Pengguna;
use App\Services\BukuBesar;
use Illuminate\Support\Carbon;

if (! function_exists('lepasDanaKe')) {
    function lepasDanaKe(Pengguna $penjual, int $rupiah, string $rujukan): void
    {
        $buku = app(BukuBesar::class);
        $luar = $buku->dompetSistem(JenisDompet::Eksternal);
        $escrow = $buku->dompetSistem(JenisDompet::Escrow);
        $jumlah = Uang::dari($rupiah);
        $buku->pindahkan($luar, $escrow, $jumlah, JenisMutasi::DanaMasuk, $rujukan);
        $buku->pindahkan($escrow, $buku->dompetPenjual($penjual), $jumlah, JenisMutasi::LepasDana, $rujukan);
    }
}

if (! function_exists('tarikDanaDari')) {
    function tarikDanaDari(Pengguna $penjual, int $rupiah, string $rujukan): void
    {
        $buku = app(BukuBesar::class);
        $dompet = $buku->dompetPenjual($penjual);
        $luar = $buku->dompetSistem(JenisDompet::Eksternal);
        $jumlah = Uang::dari($rupiah);
        $buku->pindahkan($dompet, $luar, $jumlah, JenisMutasi::Pencairan, $rujukan);
    }
}

it('menolak jenis mutasi di luar enum dengan status 422', function () {
    $sri = Pengguna::factory()->penjual()->create();

    $this->sebagai($sri)->getJson('/api/v1/dompet/mutasi?jenis=bukan_enum')
        ->assertUnprocessable()
        ->assertJsonStructure(['galat' => ['rincian' => ['jenis']]]);
});

it('menolak format tanggal yang tidak valid dengan status 422', function () {
    $sri = Pengguna::factory()->penjual()->create();

    $this->sebagai($sri)->getJson('/api/v1/dompet/mutasi?dari=bukan-tanggal')
        ->assertUnprocessable()
        ->assertJsonStructure(['galat' => ['rincian' => ['dari']]]);

    $this->sebagai($sri)->getJson('/api/v1/dompet/mutasi?sampai=bukan-tanggal')
        ->assertUnprocessable()
        ->assertJsonStructure(['galat' => ['rincian' => ['sampai']]]);
});

it('menolak tanggal terbalik (dari > sampai) dengan status 422', function () {
    $sri = Pengguna::factory()->penjual()->create();

    $this->sebagai($sri)->getJson('/api/v1/dompet/mutasi?dari=2026-11-20&sampai=2026-11-10')
        ->assertUnprocessable()
        ->assertJsonStructure(['galat' => ['rincian' => ['dari']]]);
});

it('menyaring mutasi menurut jenis mutasi', function () {
    $sri = Pengguna::factory()->penjual()->create();
    lepasDanaKe($sri, 500_000, 'LPK-LATIHAN-001');
    tarikDanaDari($sri, 200_000, 'LPK-LATIHAN-002');

    $this->sebagai($sri)->getJson('/api/v1/dompet/mutasi?jenis=lepas_dana')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.jenis', 'lepas_dana')
        ->assertJsonPath('data.0.rujukan', 'LPK-LATIHAN-001');

    $this->sebagai($sri)->getJson('/api/v1/dompet/mutasi?jenis=pencairan')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.jenis', 'pencairan')
        ->assertJsonPath('data.0.rujukan', 'LPK-LATIHAN-002');
});

it('menyaring mutasi menurut rentang tanggal dari dan sampai', function () {
    $sri = Pengguna::factory()->penjual()->create();
    lepasDanaKe($sri, 100_000, 'LPK-LATIHAN-TGL-01');
    lepasDanaKe($sri, 200_000, 'LPK-LATIHAN-TGL-02');
    lepasDanaKe($sri, 300_000, 'LPK-LATIHAN-TGL-03');

    MutasiSaldo::where('rujukan', 'LPK-LATIHAN-TGL-01')
        ->update(['created_at' => Carbon::parse('2026-10-01 10:00:00')]);
    MutasiSaldo::where('rujukan', 'LPK-LATIHAN-TGL-02')
        ->update(['created_at' => Carbon::parse('2026-10-15 14:30:00')]);
    MutasiSaldo::where('rujukan', 'LPK-LATIHAN-TGL-03')
        ->update(['created_at' => Carbon::parse('2026-10-31 09:15:00')]);

    // Filter tanggal 'dari' saja
    $this->sebagai($sri)->getJson('/api/v1/dompet/mutasi?dari=2026-10-10')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.rujukan', 'LPK-LATIHAN-TGL-03')
        ->assertJsonPath('data.1.rujukan', 'LPK-LATIHAN-TGL-02');

    // Filter tanggal 'sampai' saja
    $this->sebagai($sri)->getJson('/api/v1/dompet/mutasi?sampai=2026-10-20')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.rujukan', 'LPK-LATIHAN-TGL-02')
        ->assertJsonPath('data.1.rujukan', 'LPK-LATIHAN-TGL-01');

    // Filter rentang 'dari' dan 'sampai'
    $this->sebagai($sri)->getJson('/api/v1/dompet/mutasi?dari=2026-10-10&sampai=2026-10-20')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.rujukan', 'LPK-LATIHAN-TGL-02');

    // Filter tanggal sama persis (boundary check startOfDay s/d endOfDay)
    $this->sebagai($sri)->getJson('/api/v1/dompet/mutasi?dari=2026-10-15&sampai=2026-10-15')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.rujukan', 'LPK-LATIHAN-TGL-02');
});
