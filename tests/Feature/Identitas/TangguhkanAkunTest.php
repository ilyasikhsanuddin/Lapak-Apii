<?php

use App\Enums\StatusAkun;
use App\Models\Pengguna;
use App\Services\PenerbitSesi;

beforeEach(function () {
    $this->admin = Pengguna::factory()->admin()->create();
    $this->tini = Pengguna::factory()->pembeli()->create([
        'surel' => 'tini@lapak.test', 'kata_sandi' => 'BatikSolo2026',
    ]);
});

function alamatStatus(Pengguna $sasaran, string $tindakan): string
{
    return "/api/v1/admin/pengguna/{$sasaran->id}/{$tindakan}";
}

it('menangguhkan akun dan mencabut seluruh sesinya', function () {
    [, $kunciTini] = app(PenerbitSesi::class)->terbitkan($this->tini, 'Ponsel Tini');
    $this->denganKunci($kunciTini)->getJson('/api/v1/saya')->assertOk();

    $this->sebagai($this->admin)->postJson(alamatStatus($this->tini, 'tangguhkan'))
        ->assertOk()
        ->assertJsonPath('data.status', 'ditangguhkan');

    expect($this->tini->refresh()->status)->toBe(StatusAkun::Ditangguhkan);

    $this->denganKunci($kunciTini)->getJson('/api/v1/saya')->assertUnauthorized();
});

it('menolak akun yang ditangguhkan masuk, dan menerimanya lagi setelah dipulihkan', function () {
    $this->sebagai($this->admin)->postJson(alamatStatus($this->tini, 'tangguhkan'))->assertOk();

    $masuk = fn () => $this->tanpaKunci()->postJson('/api/v1/masuk', [
        'surel' => 'tini@lapak.test', 'kata_sandi' => 'BatikSolo2026',
    ]);

    $masuk()->assertForbidden()->assertJsonPath('galat.kode', 'AKUN_DITANGGUHKAN');

    $this->sebagai($this->admin)->postJson(alamatStatus($this->tini, 'pulihkan'))
        ->assertOk()
        ->assertJsonPath('data.status', 'aktif');

    $masuk()->assertCreated();
});

it('tidak menghidupkan lagi sesi lama saat akun dipulihkan', function () {
    [, $kunciLama] = app(PenerbitSesi::class)->terbitkan($this->tini, 'Ponsel Tini');

    $this->sebagai($this->admin);
    $this->postJson(alamatStatus($this->tini, 'tangguhkan'))->assertOk();
    $this->postJson(alamatStatus($this->tini, 'pulihkan'))->assertOk();

    $this->denganKunci($kunciLama)->getJson('/api/v1/saya')->assertUnauthorized();
});

it('melarang admin menangguhkan atau memulihkan akunnya sendiri', function (string $tindakan) {
    $this->sebagai($this->admin)->postJson(alamatStatus($this->admin, $tindakan))
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');

    expect($this->admin->refresh()->status)->toBe(StatusAkun::Aktif);
})->with(['tangguhkan', 'pulihkan']);

it('menolak siapa pun yang tidak memegang izin akun.tangguhkan', function (string $pemegang) {
    $pelaku = Pengguna::factory()->{$pemegang}()->create();

    $this->sebagai($pelaku)->postJson(alamatStatus($this->tini, 'tangguhkan'))
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');

    expect($this->tini->refresh()->status)->toBe(StatusAkun::Aktif);
})->with(['pembeli', 'penjual', 'keuangan', 'auditor']);

it('menjawab 401 bila tanpa kunci', function () {
    $this->tanpaKunci()->postJson(alamatStatus($this->tini, 'tangguhkan'))->assertUnauthorized();
});

it('menuntut sesi segar (LP-15)', function () {
    $this->sebagai($this->admin, segar: false)
        ->postJson(alamatStatus($this->tini, 'tangguhkan'))
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');

    expect($this->tini->refresh()->status)->toBe(StatusAkun::Aktif);
});

it('bersifat idempoten: mengulang tidak mengubah apa pun dan tetap menjawab 200', function () {
    $this->sebagai($this->admin);

    $this->postJson(alamatStatus($this->tini, 'tangguhkan'))->assertOk();
    $this->postJson(alamatStatus($this->tini, 'tangguhkan'))->assertOk();
    $this->postJson(alamatStatus($this->tini, 'pulihkan'))->assertOk();
    $this->postJson(alamatStatus($this->tini, 'pulihkan'))->assertOk();

    expect($this->tini->refresh()->status)->toBe(StatusAkun::Aktif);
});

it('menjawab 404 untuk akun yang tidak ada atau alamat yang bukan angka', function () {
    $this->sebagai($this->admin);

    $this->postJson('/api/v1/admin/pengguna/999999/tangguhkan')->assertNotFound();
    $this->postJson('/api/v1/admin/pengguna/abc/tangguhkan')->assertNotFound();
});
