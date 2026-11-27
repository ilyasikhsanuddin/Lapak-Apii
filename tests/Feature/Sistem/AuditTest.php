<?php

use App\Enums\PeristiwaAudit;
use App\Models\Pengguna;
use App\Services\PencatatAudit;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->auditor = Pengguna::factory()->auditor()->create();
});

it('dapat dibaca auditor dengan sesi segar, terbaru lebih dulu', function () {
    $pencatat = app(PencatatAudit::class);
    $pencatat->catat(PeristiwaAudit::TokoDisetujui, null, 'toko:1');
    $pencatat->catat(PeristiwaAudit::TokoDitangguhkan, null, 'toko:1');

    $this->sebagai($this->auditor)->getJson('/api/v1/audit')
        ->assertOk()
        ->assertJsonPath('data.0.peristiwa', 'toko.ditangguhkan')
        ->assertJsonPath('data.1.peristiwa', 'toko.disetujui');
});

it('dapat menggabungkan saringan peristiwa, sasaran, dan pelaku', function () {
    $pencatat = app(PencatatAudit::class);
    $pelaku = Pengguna::factory()->create();
    $pencatat->catat(PeristiwaAudit::TokoDisetujui, $pelaku, 'toko:2');
    $pencatat->catat(PeristiwaAudit::TokoDitangguhkan, $pelaku, 'toko:1');
    $pencatat->catat(PeristiwaAudit::TokoDisetujui, null, 'toko:1');
    $cocok = $pencatat->catat(PeristiwaAudit::TokoDisetujui, $pelaku, 'toko:1');

    $this->sebagai($this->auditor)
        ->getJson("/api/v1/audit?peristiwa=toko.disetujui&sasaran=toko:1&pelaku_id={$pelaku->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $cocok->id);
});

it('menolak peristiwa yang tidak dikenal pada penyaring', function () {
    $this->sebagai($this->auditor)->getJson('/api/v1/audit?peristiwa=ngawur')
        ->assertUnprocessable();
});

it('mencetak jangkar pada /audit/rantai', function () {
    app(PencatatAudit::class)->catat(PeristiwaAudit::TokoDisetujui, null, 'toko:1');

    $this->sebagai($this->auditor)->getJson('/api/v1/audit/rantai')
        ->assertOk()
        ->assertJsonPath('data.jumlah', 1)
        ->assertJsonPath('data.jangkar', fn (string $j) => (bool) preg_match('/^1:[0-9a-f]{64}$/', $j));
});

it('hanya terbuka bagi auditor: staf keuangan dan admin pun ditolak', function (string $pemegang) {
    $this->sebagai(Pengguna::factory()->{$pemegang}()->create())
        ->getJson('/api/v1/audit')
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');
})->with(['pembeli', 'penjual', 'admin', 'keuangan']);

it('menuntut sesi segar (LP-15)', function (string $rute) {
    $this->sebagai($this->auditor, segar: false)->getJson($rute)
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');
})->with(['/api/v1/audit', '/api/v1/audit/rantai']);

it('menuntut masuk terlebih dahulu', function () {
    $this->getJson('/api/v1/audit')->assertUnauthorized();
});

it('tidak memiliki satu pun pintu yang menulis ke log audit', function () {
    $menulis = collect(Route::getRoutes())
        ->filter(fn ($rute) => str_starts_with($rute->uri(), 'api/v1/audit'))
        ->flatMap(fn ($rute) => array_diff($rute->methods(), ['GET', 'HEAD']));

    expect($menulis->all())->toBe([]);
});
