<?php

use App\Models\Pengguna;
use App\Services\PenerbitSesi;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->sri = Pengguna::factory()->create([
        'surel' => 'sri@lapak.test', 'kata_sandi' => 'BatikSolo2026',
    ]);

    $this->terbit = fn (string $perangkat) => app(PenerbitSesi::class)->terbitkan($this->sri, $perangkat);

    $this->ubah = fn (array $timpa = []) => $this->putJson('/api/v1/saya/kata-sandi', array_merge([
        'kata_sandi_baru' => 'KainSogan2027',
        'konfirmasi_kata_sandi_baru' => 'KainSogan2027',
    ], $timpa));
});

it('mengganti kata sandi sehingga hanya yang baru dapat dipakai masuk', function () {
    $this->sebagai($this->sri);
    ($this->ubah)()->assertOk();

    $this->tanpaKunci()->postJson('/api/v1/masuk', [
        'surel' => 'sri@lapak.test', 'kata_sandi' => 'KainSogan2027',
    ])->assertCreated();

    $this->tanpaKunci()->postJson('/api/v1/masuk', [
        'surel' => 'sri@lapak.test', 'kata_sandi' => 'BatikSolo2026',
    ])->assertUnauthorized();
});

it('mencabut seluruh sesi di perangkat lain: kuncinya menjawab 401', function () {
    [, $kunciPonsel] = ($this->terbit)('Ponsel');
    [, $kunciTablet] = ($this->terbit)('Tablet');
    $this->sebagai($this->sri);

    ($this->ubah)()->assertOk();

    $this->denganKunci($kunciPonsel)->getJson('/api/v1/saya')->assertUnauthorized();
    $this->denganKunci($kunciTablet)->getJson('/api/v1/saya')->assertUnauthorized();
});

it('mengganti kunci sesi yang sedang dipakai, sesinya tetap sama', function () {
    [$sesi, $kunciLama] = ($this->terbit)('Laptop');
    $this->denganKunci($kunciLama);

    $respons = ($this->ubah)()->assertOk();
    $kunciBaru = $respons->json('data.kunci');

    expect($kunciBaru)->toMatch('/^lpk_[0-9A-Z]{26}\.[0-9a-f]{64}$/')
        ->and($kunciBaru)->not->toBe($kunciLama);

    // Kunci baru dicoba LEBIH DULU: memakai kunci lama kini mencabut sesinya (LP-14).
    $this->denganKunci($kunciBaru)->getJson('/api/v1/saya')
        ->assertOk()
        ->assertJsonPath('data.sesi.pengenal', $sesi->pengenal);
    $this->denganKunci($kunciLama)->getJson('/api/v1/saya')->assertUnauthorized();
});

it('menuntut sesi segar dan tidak mengubah apa pun bila tidak segar (LP-15)', function () {
    [, $kunciPonsel] = ($this->terbit)('Ponsel');
    $this->sebagai($this->sri, segar: false);

    ($this->ubah)()
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');

    expect(Hash::check('BatikSolo2026', $this->sri->refresh()->kata_sandi))->toBeTrue();
    $this->denganKunci($kunciPonsel)->getJson('/api/v1/saya')->assertOk();
});

it('menjawab 401 bila tanpa kunci', function () {
    $this->tanpaKunci()->putJson('/api/v1/saya/kata-sandi', [
        'kata_sandi_baru' => 'KainSogan2027', 'konfirmasi_kata_sandi_baru' => 'KainSogan2027',
    ])->assertUnauthorized();
});

it('menolak kata sandi baru yang lemah tanpa mencabut sesi apa pun', function (string $sandi) {
    [, $kunciPonsel] = ($this->terbit)('Ponsel');
    $this->sebagai($this->sri);

    ($this->ubah)(['kata_sandi_baru' => $sandi, 'konfirmasi_kata_sandi_baru' => $sandi])
        ->assertUnprocessable()
        ->assertJsonStructure(['galat' => ['rincian' => ['kata_sandi_baru']]]);

    $this->denganKunci($kunciPonsel)->getJson('/api/v1/saya')->assertOk();
})->with([
    'terlalu pendek' => ['Batik2026'],
    'tanpa angka' => ['BatikSoloRaya'],
    'tanpa huruf' => ['1234567890123'],
]);

it('menolak konfirmasi kata sandi baru yang tidak cocok', function () {
    $this->sebagai($this->sri);

    ($this->ubah)(['konfirmasi_kata_sandi_baru' => 'KainSogan2028'])
        ->assertUnprocessable()
        ->assertJsonPath('galat.rincian.konfirmasi_kata_sandi_baru.0', 'Konfirmasi kata sandi tidak cocok.');
});
