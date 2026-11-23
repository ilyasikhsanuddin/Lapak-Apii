<?php

use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Models\Pengguna;
use App\Services\BukuBesar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->sri = Pengguna::factory()->penjual()->create(['nama' => 'Sri Wahyuni']);
    $this->keuangan = Pengguna::factory()->keuangan()->create();

    $buku = app(BukuBesar::class);
    $luar = $buku->dompetSistem(JenisDompet::Eksternal);
    $dompetSri = $buku->dompetPenjual($this->sri);

    $this->kelompok = $buku->pindahkan($luar, $dompetSri, Uang::dari(150_000), JenisMutasi::DanaMasuk, 'LPK-UJI-4001');
    $this->kodeDompetSri = $dompetSri->kode;
});

it('menampilkan kedua baris mutasi pemindahan beserta kode dompetnya bagi keuangan dan auditor', function (string $pemegang) {
    $staf = Pengguna::factory()->{$pemegang}()->create();

    $this->sebagai($staf)
        ->getJson("/api/v1/buku-besar/kelompok/{$this->kelompok}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.kelompok', $this->kelompok)
        ->assertJsonPath('meta.jumlah_baris', 2)
        ->assertJsonPath('meta.seimbang', true)
        ->assertJsonPath('data.0.dompet.kode', 'eksternal')
        ->assertJsonPath('data.0.jumlah.rupiah', -150_000)
        ->assertJsonPath('data.1.dompet.kode', $this->kodeDompetSri)
        ->assertJsonPath('data.1.jumlah.rupiah', 150_000);
})->with(['keuangan', 'auditor']);

it('menjawab 404 untuk UUID kelompok yang tidak ditemukan', function () {
    $uuidKosong = (string) Str::uuid();

    $this->sebagai($this->keuangan)
        ->getJson("/api/v1/buku-besar/kelompok/{$uuidKosong}")
        ->assertNotFound()
        ->assertJsonPath('galat.kode', 'TIDAK_DITEMUKAN');
});

it('menjawab 404 untuk nilai bukan UUID tanpa menyentuh basis data', function (string $bukanUuid) {
    $this->sebagai($this->keuangan);

    // Hangatkan cache izin terlebih dahulu agar kueri yang dihitung murni milik controller.
    $this->getJson('/api/v1/buku-besar/dompet')->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->getJson("/api/v1/buku-besar/kelompok/{$bukanUuid}")
        ->assertNotFound()
        ->assertJsonPath('galat.kode', 'TIDAK_DITEMUKAN');

    $kueriMutasi = array_filter(
        DB::getQueryLog(),
        fn (array $kueri): bool => str_contains($kueri['query'], 'mutasi_saldo') || str_contains($kueri['query'], 'dompet'),
    );
    DB::disableQueryLog();

    expect($kueriMutasi)->toBeEmpty();
})->with([
    'angka biasa' => ['12345'],
    'teks acak' => ['bukan-uuid-sama-sekali'],
    'uuid kurang satu karakter' => ['01935a7b-1234-7000-8000-00000000000'],
]);

it('menolak peran tanpa izin buku-besar.lihat dan menuntut sesi segar (LP-15)', function () {
    $admin = Pengguna::factory()->admin()->create();
    $this->sebagai($admin)
        ->getJson("/api/v1/buku-besar/kelompok/{$this->kelompok}")
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');

    $this->sebagai($this->keuangan, segar: false)
        ->getJson("/api/v1/buku-besar/kelompok/{$this->kelompok}")
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');
});
