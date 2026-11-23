<?php

use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Models\Dompet;
use App\Models\Pengguna;
use App\Services\BukuBesar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->sri = Pengguna::factory()->penjual()->create([
        'nama' => 'Sri Wahyuni',
    ]);

    $this->keuangan = Pengguna::factory()->keuangan()->create();
    $this->auditor = Pengguna::factory()->auditor()->create();

    // Rp 200.000 masuk escrow, lalu Rp 150.000 dilepas ke dompet Sri.
    $buku = app(BukuBesar::class);

    $luar = $buku->dompetSistem(JenisDompet::Eksternal);
    $escrow = $buku->dompetSistem(JenisDompet::Escrow);
    $this->dompetSri = $buku->dompetPenjual($this->sri);

    $buku->pindahkan(
        $luar,
        $escrow,
        Uang::dari(200_000),
        JenisMutasi::DanaMasuk,
        'LPK-UJI-3001',
    );

    $buku->pindahkan(
        $escrow,
        $this->dompetSri,
        Uang::dari(150_000),
        JenisMutasi::LepasDana,
        'LPK-UJI-3001',
    );
});

it('mengizinkan staf keuangan dan auditor melihat seluruh dompet', function () {
    $this->sebagai($this->keuangan)
        ->getJson('/api/v1/buku-besar/dompet')
        ->assertOk()
        ->assertJsonPath('meta.jumlah_dompet', 3);

    $this->sebagai($this->auditor)
        ->getJson('/api/v1/buku-besar/dompet')
        ->assertOk()
        ->assertJsonPath('meta.jumlah_dompet', 3);
});

it('menolak admin penjual dan pembeli dari seluruh pintu buku besar', function () {
    foreach ([
        Pengguna::factory()->admin()->create(),
        Pengguna::factory()->penjual()->create(),
        Pengguna::factory()->pembeli()->create(),
    ] as $pengguna) {
        $this->sebagai($pengguna)
            ->getJson('/api/v1/buku-besar/dompet')
            ->assertForbidden();

        $this->getJson('/api/v1/buku-besar/dompet/'.$this->dompetSri->id.'/mutasi')
            ->assertForbidden();
    }
});

it('menolak penjual yang mencoba membaca dompet dengan id pilihan sendiri', function () {
    $this->sebagai($this->sri)
        ->getJson('/api/v1/buku-besar/dompet')
        ->assertForbidden();

    $this->getJson('/api/v1/buku-besar/dompet/999999/mutasi')
        ->assertForbidden();
});

it('menampilkan identitas pemilik dan saldo setiap dompet kepada staf', function () {
    $respons = $this->sebagai($this->keuangan)
        ->getJson('/api/v1/buku-besar/dompet')
        ->assertOk()
        ->assertJsonPath('meta.seimbang', true)
        ->assertJsonPath('meta.jumlah_seluruh_saldo', 0);

    $data = collect($respons->json('data'));

    $dompetSri = $data->firstWhere('id', $this->dompetSri->id);

    expect($dompetSri['pemilik']['id'])->toBe($this->sri->id)
        ->and($dompetSri['pemilik']['nama'])->toBe('Sri Wahyuni')
        ->and($dompetSri['saldo']['rupiah'])->toBe(150_000)
        ->and($dompetSri['saldo']['tampilan'])->toBe('Rp 150.000');

    $dompetEksternal = $data->firstWhere('jenis', 'eksternal');

    expect($dompetEksternal['pemilik'])->toBeNull()
        ->and($dompetEksternal['saldo']['rupiah'])->toBe(-200_000)
        ->and($dompetEksternal['saldo']['tampilan'])->toBe('-Rp 200.000');
});

it('menampilkan riwayat mutasi dompet staf dengan kelompok dan urutan terbaru', function () {
    $this->sebagai($this->keuangan)
        ->getJson('/api/v1/buku-besar/dompet/'.$this->dompetSri->id.'/mutasi')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.rujukan', 'LPK-UJI-3001')
        ->assertJsonPath('data.0.jenis', 'lepas_dana')
        ->assertJsonPath('data.0.jumlah.rupiah', 150_000)
        ->assertJsonPath('data.0.saldo_setelah', 150_000)
        ->assertJsonStructure([
            'data' => [
                ['id', 'kelompok', 'jenis', 'jumlah', 'saldo_setelah', 'rujukan'],
            ],
        ]);
});

it('melaporkan buku besar seimbang ketika seluruh pemindahan sah', function () {
    $this->sebagai($this->auditor)
        ->getJson('/api/v1/buku-besar/dompet')
        ->assertOk()
        ->assertJsonPath('meta.seimbang', true)
        ->assertJsonPath('meta.jumlah_seluruh_saldo', 0);
});

it('melaporkan buku besar tidak seimbang jika saldo menyimpang', function () {
    $luar = Dompet::query()->where('jenis', JenisDompet::Eksternal)->firstOrFail();

    DB::table('dompet')
        ->where('id', $luar->id)
        ->update(['saldo_rupiah' => $luar->saldo_rupiah + 849_999]);

    $this->sebagai($this->keuangan)
        ->getJson('/api/v1/buku-besar/dompet')
        ->assertOk()
        ->assertJsonPath('meta.seimbang', false)
        ->assertJsonPath('meta.jumlah_seluruh_saldo', 849_999);
});

it('hanya menyediakan metode baca pada seluruh rute buku besar', function () {
    $ruteTulis = [];

    foreach (Route::getRoutes() as $rute) {
        if (! str_starts_with($rute->uri(), 'api/v1/buku-besar')) {
            continue;
        }

        foreach (array_diff($rute->methods(), ['GET', 'HEAD']) as $metode) {
            $ruteTulis[] = $metode.' '.$rute->uri();
        }
    }

    expect($ruteTulis)->toBeEmpty();
});

it('menuntut sesi segar untuk membaca buku besar', function () {
    $this->sebagai($this->keuangan, segar: false)
        ->getJson('/api/v1/buku-besar/dompet')
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');
});

it('menampilkan kelompok yang sama untuk dua sisi satu pemindahan', function () {
    $buku = app(BukuBesar::class);

    $luar = Dompet::query()->where('jenis', JenisDompet::Eksternal)->firstOrFail();
    $baris = $luar->mutasi()->where('rujukan', 'LPK-UJI-3001')->firstOrFail();

    $pasangan = DB::table('mutasi_saldo')
        ->where('kelompok', $baris->kelompok)
        ->orderBy('id')
        ->get();

    expect($pasangan)->toHaveCount(2)
        ->and((int) $pasangan->sum('jumlah_rupiah'))->toBe(0)
        ->and($pasangan->pluck('kelompok')->unique())->toHaveCount(1);

    $this->sebagai($this->auditor)
        ->getJson('/api/v1/buku-besar/dompet/'.$luar->id.'/mutasi')
        ->assertOk()
        ->assertJsonPath('data.0.kelompok', $baris->kelompok);
});
