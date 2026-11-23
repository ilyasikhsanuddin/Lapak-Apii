<?php

use Illuminate\Support\Facades\DB;

it('mencetak setiap rute terlindung dengan izin dan pemegangnya', function () {
    $this->artisan('lapak:matriks-rute')
        ->expectsOutputToContain('api/v1/admin')
        ->expectsOutputToContain('rute terlindung')
        ->assertSuccessful();
});

it('gagal dan memperingatkan bila ada izin tanpa pemegang', function () {
    DB::table('peran_izin')->delete();

    $this->artisan('lapak:matriks-rute')
        ->expectsOutputToContain('TIDAK ADA PEMEGANG')
        ->assertFailed();
});
