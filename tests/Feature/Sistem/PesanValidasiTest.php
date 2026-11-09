<?php

use Illuminate\Support\Arr;

it('memakai locale id', function () {
    expect(config('app.locale'))->toBe('id');
});

it('memuat semua kunci validasi bawaan Laravel', function () {
    $en = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
    $id = require lang_path('id/validation.php');

    $hilang = array_diff(
        array_keys(Arr::dot(Arr::except($en, ['custom', 'attributes']))),
        array_keys(Arr::dot($id)),
    );

    expect(array_values($hilang))->toBe([]);
});

it('menerjemahkan nama atribut registrasi', function () {
    foreach (['nama', 'surel', 'telepon', 'kata_sandi', 'konfirmasi_kata_sandi'] as $atribut) {
        expect(trans("validation.attributes.$atribut"))->not->toBe("validation.attributes.$atribut");
    }
});

it('mengirim pesan 422 berbahasa Indonesia tanpa sisa bahasa Inggris', function () {
    $respons = $this->postJson('/api/v1/registrasi', [])
        ->assertUnprocessable()
        ->assertJsonPath('galat.rincian.nama.0', 'nama wajib diisi.')
        ->assertJsonPath('galat.rincian.surel.0', 'surel wajib diisi.')
        ->assertJsonPath('galat.rincian.kata_sandi.0', 'kata sandi wajib diisi.');

    $semua = collect($respons->json('galat.rincian'))->flatten()->implode(' ');

    expect($semua)->not->toMatch('/\b(the|field|must|required|invalid|taken|least|characters)\b/i');
});

it('menerjemahkan pesan aturan kata sandi', function () {
    $this->postJson('/api/v1/registrasi', [
        'nama' => 'Dewi Anggraini',
        'surel' => 'dewi@contoh.test',
        'kata_sandi' => 'Batik2026',
        'konfirmasi_kata_sandi' => 'Batik2026',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('galat.rincian.kata_sandi.0', 'kata sandi minimal 10 karakter.');
});