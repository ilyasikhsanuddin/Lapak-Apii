<?php

use App\Enums\NamaPeran;
use App\Enums\StatusToko;
use App\Exceptions\TransisiTokoTidakSah;
use App\Models\Pengguna;
use App\Models\Toko;
use App\Services\ModerasiToko;
use App\Services\PenerbitSesi;

beforeEach(function () {
    $this->admin = Pengguna::factory()->admin()->create();
    $this->tini = Pengguna::factory()->pembeli()->create(['nama' => 'Tini Lestari']);
    $this->tokoTini = Toko::factory()->menunggu()->for($this->tini, 'pemilik')
        ->create(['nama' => 'Wedang Uwuh Bu Tini']);
});

it('menolak pengajuan toko dengan alasan wajib, tanpa memberi peran penjual atau mencabut sesi', function () {
    [, $kunciTini] = app(PenerbitSesi::class)->terbitkan($this->tini, 'Ponsel Tini');

    $this->sebagai($this->admin)->postJson("/api/v1/admin/toko/{$this->tokoTini->id}/tolak", [
        'alasan' => 'Alamat toko belum mencantumkan kecamatan.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'ditolak')
        ->assertJsonPath('data.catatan_moderasi', 'Alamat toko belum mencantumkan kecamatan.');

    $toko = $this->tokoTini->refresh();
    expect($toko->status)->toBe(StatusToko::Ditolak)
        ->and($toko->dimoderasi_oleh)->toBe($this->admin->id)
        ->and($toko->dimoderasi_pada)->not->toBeNull()
        ->and($this->tini->punyaPeran(NamaPeran::Penjual))->toBeFalse();

    // Pemilik tetap masuk dan dapat melihat alasan penolakan pada toko-saya.
    $this->denganKunci($kunciTini)->getJson('/api/v1/toko-saya')
        ->assertOk()
        ->assertJsonPath('data.status', 'ditolak')
        ->assertJsonPath('data.catatan_moderasi', 'Alamat toko belum mencantumkan kecamatan.')
        ->assertJsonMissingPath('data.dimoderasi_oleh');
});

it('menolak penolakan tanpa alasan yang memadai atau tanpa sesi segar', function () {
    $this->sebagai($this->admin)->postJson("/api/v1/admin/toko/{$this->tokoTini->id}/tolak", [])
        ->assertUnprocessable();

    $this->postJson("/api/v1/admin/toko/{$this->tokoTini->id}/tolak", ['alasan' => 'Kurang'])
        ->assertUnprocessable();

    $this->sebagai($this->admin, segar: false)
        ->postJson("/api/v1/admin/toko/{$this->tokoTini->id}/tolak", [
            'alasan' => 'Alasan penolakan minimal sepuluh karakter.',
        ])
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');
});

it('menolak perpindahan tidak sah dengan status 409 (LP-3)', function (StatusToko $statusAwal) {
    $toko = Toko::factory()->create(['status' => $statusAwal]);

    $this->sebagai($this->admin)->postJson("/api/v1/admin/toko/{$toko->id}/tolak", [
        'alasan' => 'Alasan penolakan minimal sepuluh karakter.',
    ])
        ->assertStatus(409)
        ->assertJsonPath('galat.kode', 'LP-3');
})->with([
    'sudah aktif' => [StatusToko::Aktif],
    'sedang ditangguhkan' => [StatusToko::Ditangguhkan],
    'sudah ditolak' => [StatusToko::Ditolak],
]);

it('membolehkan pemilik toko yang ditolak mengajukan ulang lewat PATCH /toko-saya', function () {
    app(ModerasiToko::class)->tolak($this->tokoTini, $this->admin, 'Deskripsi usaha terlalu singkat.');

    // Selama ditolak, tetap belum menjadi penjual.
    $this->sebagai($this->tini)->getJson('/api/v1/toko-saya/produk')->assertForbidden();

    // Membenahi data lewat PATCH /toko-saya mengembalikan status ke menunggu.
    $this->sebagai($this->tini)->patchJson('/api/v1/toko-saya', [
        'deskripsi' => 'Minuman rempah tradisional khas Karanganyar.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'menunggu')
        ->assertJsonPath('data.catatan_moderasi', null);

    expect($this->tokoTini->refresh()->status)->toBe(StatusToko::Menunggu);

    // Admin kini dapat menyetujui pengajuan ulang tersebut.
    $this->sebagai($this->admin)->postJson("/api/v1/admin/toko/{$this->tokoTini->id}/setujui")
        ->assertOk()
        ->assertJsonPath('data.status', 'aktif');
});

it('menjaga status toko aktif tetap aktif saat profil diperbarui', function () {
    $sri = Pengguna::factory()->penjual()->create();
    $toko = Toko::factory()->for($sri, 'pemilik')->create(['status' => StatusToko::Aktif]);

    $this->sebagai($sri)->patchJson('/api/v1/toko-saya', ['kota' => 'Sukoharjo'])
        ->assertOk()
        ->assertJsonPath('data.status', 'aktif');

    expect(fn () => app(ModerasiToko::class)->ajukanUlang($toko->refresh()))
        ->toThrow(TransisiTokoTidakSah::class);
});
