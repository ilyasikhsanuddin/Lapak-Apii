<?php

declare(strict_types=1);

use App\Enums\NamaIzin;
use Illuminate\Support\Facades\Route;

/**
 * Mengambil seluruh izin yang dipasang pada route API v1.
 *
 * @return array<string, list<string>>
 */
function daftarIzinPadaRute(): array
{
    $hasil = [];

    foreach (Route::getRoutes() as $rute) {
        if (! str_starts_with($rute->uri(), 'api/v1')) {
            continue;
        }

        foreach ($rute->gatherMiddleware() as $middleware) {
            if (! str_starts_with($middleware, 'izin:')) {
                continue;
            }

            $namaIzin = explode(',', substr($middleware, 5));

            foreach ($namaIzin as $izin) {
                $hasil[$rute->uri()][] = trim($izin);
            }
        }
    }

    return $hasil;
}

it('memakai setiap izin yang didefinisikan pada minimal satu rute', function () {
    $didefinisikan = array_map(
        fn (NamaIzin $izin) => $izin->value,
        NamaIzin::cases(),
    );

    $dipakai = [];

    foreach (daftarIzinPadaRute() as $izinRute) {
        $dipakai = [...$dipakai, ...$izinRute];
    }

    $belumDipakai = array_values(array_diff($didefinisikan, array_unique($dipakai)));

    expect($belumDipakai)->toBeEmpty(
        'Izin yang belum dipakai oleh rute: '.implode(', ', $belumDipakai)
    );
});

it('tidak memakai izin yang tidak didefinisikan pada enum', function () {
    $didefinisikan = array_map(
        fn (NamaIzin $izin) => $izin->value,
        NamaIzin::cases(),
    );

    $dipakai = [];

    foreach (daftarIzinPadaRute() as $izinRute) {
        $dipakai = [...$dipakai, ...$izinRute];
    }

    $tidakDikenal = array_values(array_diff(array_unique($dipakai), $didefinisikan));

    expect($tidakDikenal)->toBeEmpty(
        'Izin yang tidak dikenal: '.implode(', ', $tidakDikenal)
    );
});

it('memastikan setiap rute berizin juga dilindungi auth:sesi', function () {
    $tanpaAutentikasi = [];

    foreach (Route::getRoutes() as $rute) {
        if (! str_starts_with($rute->uri(), 'api/v1')) {
            continue;
        }

        $middleware = $rute->gatherMiddleware();

        $memakaiIzin = collect($middleware)->contains(
            fn (string $item) => str_starts_with($item, 'izin:')
        );

        if ($memakaiIzin && ! in_array('auth:sesi', $middleware, true)) {
            $tanpaAutentikasi[] = $rute->uri();
        }
    }

    expect($tanpaAutentikasi)->toBeEmpty(
        'Rute dengan izin tanpa auth:sesi: '.implode(', ', $tanpaAutentikasi)
    );
});
