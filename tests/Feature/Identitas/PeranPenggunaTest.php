<?php

use App\Enums\NamaPeran;
use App\Models\Pengguna;
use App\Services\PenerbitSesi;
use App\Services\PengelolaPeran;

beforeEach(function () {
    $this->admin = Pengguna::factory()->admin()->create();
    $this->tini = Pengguna::factory()->pembeli()->create();
});

function alamatPeran(Pengguna $sasaran, string $peran): string
{
    return "/api/v1/admin/pengguna/{$sasaran->id}/peran/{$peran}";
}

it('menolak siapa pun yang tidak memegang izin peran.kelola', function (string $pemegang) {
    $pelaku = Pengguna::factory()->{$pemegang}()->create();

    $this->sebagai($pelaku)->getJson('/api/v1/admin/pengguna')->assertForbidden();
    $this->putJson(alamatPeran($this->tini, 'keuangan'))
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');

    expect($this->tini->punyaPeran(NamaPeran::Keuangan))->toBeFalse();
})->with(['pembeli', 'penjual', 'keuangan', 'auditor']);

it('mencabut seluruh sesi sasaran begitu perannya berubah', function () {
    [, $kunciTini] = app(PenerbitSesi::class)->terbitkan($this->tini, 'Ponsel Tini');
    $this->denganKunci($kunciTini)->getJson('/api/v1/saya')->assertOk();

    $this->sebagai($this->admin)->putJson(alamatPeran($this->tini, 'keuangan'))->assertOk();

    // Kunci lama Tini mati. Ia masuk ulang dan baru saat itu membawa kewenangan barunya.
    $this->denganKunci($kunciTini)->getJson('/api/v1/saya')->assertUnauthorized();

    // Sesi admin yang melakukan perubahan tidak ikut tercabut.
    $this->sebagai($this->admin)->getJson('/api/v1/saya')->assertOk();
});

it('menuntut sesi segar untuk mengubah peran, tetapi tidak untuk melihat daftar (LP-15)', function () {
    $this->sebagai($this->admin, segar: false);

    $this->getJson('/api/v1/admin/pengguna')->assertOk();

    $this->putJson(alamatPeran($this->tini, 'keuangan'))
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');
    $this->deleteJson(alamatPeran($this->tini, 'pembeli'))
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');

    expect($this->tini->punyaPeran(NamaPeran::Pembeli))->toBeTrue();
});

it('melarang admin mengubah perannya sendiri', function (string $metode, string $peran) {
    $this->sebagai($this->admin)->json($metode, alamatPeran($this->admin, $peran))
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');

    expect(app(PengelolaPeran::class)->daftar($this->admin))->toBe(['admin']);
})->with([
    'menambah keuangan' => ['PUT', 'keuangan'],
    'melepas admin' => ['DELETE', 'admin'],
]);

it('membolehkan seorang admin mencabut admin lain', function () {
    $adminKedua = Pengguna::factory()->admin()->create();

    $this->sebagai($this->admin)->deleteJson(alamatPeran($adminKedua, 'admin'))
        ->assertOk()
        ->assertJsonPath('data.peran', []);
});

it('menolak peran penjual lewat pintu ini (LP-3)', function (string $metode) {
    $sri = Pengguna::factory()->penjual()->create();

    $this->sebagai($this->admin)->json($metode, alamatPeran($sri, 'penjual'))
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'LP-3');

    expect($sri->punyaPeran(NamaPeran::Penjual))->toBeTrue();
})->with(['PUT', 'DELETE']);
