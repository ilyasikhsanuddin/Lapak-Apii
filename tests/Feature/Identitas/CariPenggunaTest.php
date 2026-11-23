<?php

use App\Models\Pengguna;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = Pengguna::factory()->admin()->create();
});

function hitungKueriDaftar(Closure $kerja): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $kerja();
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}

it('mencocokkan nama atau surel', function () {
    Pengguna::factory()->pembeli()->create(['nama' => 'Tini Lestari', 'surel' => 'tini@lapak.test']);
    Pengguna::factory()->pembeli()->create(['nama' => 'Andi Nugroho', 'surel' => 'nugroho-tini@lapak.test']);
    Pengguna::factory()->pembeli()->create(['nama' => 'Wulan Sari', 'surel' => 'wulan@lapak.test']);

    $this->sebagai($this->admin);

    $this->getJson('/api/v1/admin/pengguna?cari=lestari')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.surel', 'tini@lapak.test');

    // "tini" ada pada nama Tini dan pada surel Andi.
    $this->getJson('/api/v1/admin/pengguna?cari=tini')->assertOk()->assertJsonCount(2, 'data');
});

it('memperlakukan karakter pengganti pada pencarian sebagai teks biasa', function () {
    Pengguna::factory()->create(['nama' => 'Diskon 50% Tini', 'surel' => 'diskon@lapak.test']);
    Pengguna::factory()->create(['nama' => 'Tini Lestari', 'surel' => 'tini_lestari@lapak.test']);

    $this->sebagai($this->admin);
    $cari = fn (string $kata) => $this->getJson('/api/v1/admin/pengguna?cari='.urlencode($kata))->assertOk();

    $cari('%%')->assertJsonCount(0, 'data');
    $cari('50%')->assertJsonCount(1, 'data')->assertJsonPath('data.0.surel', 'diskon@lapak.test');
    $cari('t_ni')->assertJsonCount(0, 'data');
    $cari('_lestari')->assertJsonCount(1, 'data')->assertJsonPath('data.0.surel', 'tini_lestari@lapak.test');
});

it('tetap menghormati saringan peran bersama pencarian', function () {
    Pengguna::factory()->pembeli()->create(['nama' => 'Sri Pembeli', 'surel' => 'sri-pembeli@lapak.test']);
    Pengguna::factory()->auditor()->create(['nama' => 'Sri Auditor', 'surel' => 'sri-auditor@lapak.test']);

    $this->sebagai($this->admin)->getJson('/api/v1/admin/pengguna?peran=auditor&cari=sri')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.surel', 'sri-auditor@lapak.test');
});

it('menolak kata pencarian yang terlalu pendek atau terlalu panjang', function (string $kata) {
    $this->sebagai($this->admin)->getJson('/api/v1/admin/pengguna?cari='.urlencode($kata))
        ->assertUnprocessable()
        ->assertJsonStructure(['galat' => ['rincian' => ['cari']]]);
})->with([
    'satu karakter' => ['%'],
    'terlalu panjang' => [str_repeat('a', 61)],
]);

it('tidak menambah kueri ketika jumlah pengguna bertambah (tanpa N+1)', function () {
    Pengguna::factory()->count(3)->pembeli()->create(['nama' => 'Pembeli Contoh']);

    $this->sebagai($this->admin);
    $cari = fn () => $this->getJson('/api/v1/admin/pengguna?cari=Contoh')->assertOk();

    $cari(); // pemanasan: izin admin masuk ingatan, sehingga tidak ikut terhitung

    $sedikit = hitungKueriDaftar($cari);

    Pengguna::factory()->count(30)->pembeli()->create(['nama' => 'Pembeli Contoh']);

    $banyak = hitungKueriDaftar($cari);

    // Halaman kedua kali ini memuat 12 baris, bukan 3. Satu kueri tambahan per baris berarti N+1.
    expect($banyak)->toBe($sedikit);
});
