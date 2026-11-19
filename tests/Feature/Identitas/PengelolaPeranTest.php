<?php

use App\Enums\NamaIzin;
use App\Enums\NamaPeran;
use App\Models\Pengguna;
use App\Services\PenerbitSesi;
use App\Services\PengelolaPeran;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->pengelola = app(PengelolaPeran::class);
});

it('membuat pengguna tanpa peran kecuali uji memintanya', function () {
    $daftar = fn (Pengguna $pengguna): array => $this->pengelola->daftar($pengguna);

    expect($daftar(Pengguna::factory()->create()))->toBe([])
        ->and($daftar(Pengguna::factory()->pembeli()->create()))->toBe(['pembeli'])
        ->and($daftar(Pengguna::factory()->penjual()->create()))->toBe(['pembeli', 'penjual'])
        ->and($daftar(Pengguna::factory()->admin()->create()))->toBe(['admin']);
});

it('memberi peran satu kali walau diminta berulang', function () {
    $tini = Pengguna::factory()->pembeli()->create();
    $admin = Pengguna::factory()->admin()->create();

    expect($this->pengelola->beri($tini, NamaPeran::Penjual, $admin))->toBeTrue()
        ->and($this->pengelola->beri($tini, NamaPeran::Penjual, $admin))->toBeFalse()
        ->and($this->pengelola->daftar($tini))->toBe(['pembeli', 'penjual'])
        ->and($tini->punyaPeran(NamaPeran::Penjual))->toBeTrue()
        ->and($tini->punyaIzin(NamaIzin::ProdukKelola))->toBeTrue();
});

it('mencatat siapa yang memberikan peran', function () {
    $tini = Pengguna::factory()->pembeli()->create();
    $admin = Pengguna::factory()->admin()->create();

    $this->pengelola->beri($tini, NamaPeran::Penjual, $admin);

    $pemberi = DB::table('pengguna_peran')
        ->join('peran', 'peran.id', '=', 'pengguna_peran.peran_id')
        ->where('pengguna_id', $tini->id)
        ->pluck('diberikan_oleh', 'peran.nama');

    expect($pemberi->all())->toBe(['pembeli' => null, 'penjual' => $admin->id]);
});

it('mencabut peran dan izin yang dibawanya', function () {
    $sri = Pengguna::factory()->penjual()->create();

    expect($this->pengelola->cabut($sri, NamaPeran::Penjual))->toBeTrue()
        ->and($this->pengelola->cabut($sri, NamaPeran::Penjual))->toBeFalse()
        ->and($sri->punyaIzin(NamaIzin::ProdukKelola))->toBeFalse()
        ->and($sri->punyaIzin(NamaIzin::KeranjangKelola))->toBeTrue();
});

it('mencabut seluruh sesi pengguna setiap kali perannya berubah', function () {
    $tini = Pengguna::factory()->pembeli()->create();
    $penerbit = app(PenerbitSesi::class);
    [, $kunciLaptop] = $penerbit->terbitkan($tini, 'Laptop');
    [, $kunciPonsel] = $penerbit->terbitkan($tini, 'Ponsel');

    $this->pengelola->beri($tini, NamaPeran::Penjual);

    $this->denganKunci($kunciLaptop)->getJson('/api/v1/saya')->assertUnauthorized();
    $this->denganKunci($kunciPonsel)->getJson('/api/v1/saya')->assertUnauthorized();
});

it('membiarkan sesi tetap berlaku bila tidak ada peran yang berubah', function () {
    $sri = Pengguna::factory()->penjual()->create();
    [, $kunci] = app(PenerbitSesi::class)->terbitkan($sri, 'Laptop');

    $this->pengelola->beri($sri, NamaPeran::Penjual);   // sudah dipegang
    $this->pengelola->cabut($sri, NamaPeran::Admin);    // tidak dipegang

    $this->denganKunci($kunci)->getJson('/api/v1/saya')->assertOk();
});

it('tidak menyentuh sesi pengguna lain', function () {
    $tini = Pengguna::factory()->pembeli()->create();
    $andi = Pengguna::factory()->pembeli()->create();
    [, $kunciAndi] = app(PenerbitSesi::class)->terbitkan($andi, 'Laptop Andi');

    $this->pengelola->beri($tini, NamaPeran::Penjual);

    $this->denganKunci($kunciAndi)->getJson('/api/v1/saya')->assertOk();
});

it('gagal keras, bukan 404, bila data acuan peran belum diisi', function () {
    DB::table('peran')->delete();

    $this->pengelola->beri(Pengguna::factory()->create(), NamaPeran::Pembeli);
})->throws(LogicException::class, 'Jalankan PeranIzinSeeder');
