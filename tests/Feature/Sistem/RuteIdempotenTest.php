<?php

use Illuminate\Support\Facades\Route;

/*
| LP-11, ditegakkan dengan cara yang sama seperti RUTE_PUBLIK dan matriks otorisasi.
|
| Setiap rute POST di bawah /api/v1 WAJIB tercatat pada salah satu dari dua daftar. POST
| adalah satu-satunya metode yang tidak idempoten menurut HTTP: mengulanginya dapat berefek dua
| kali. Menambah rute POST karenanya menuntut satu keputusan tertulis: dijaga Idempotency-Key,
| atau aman diulang karena sebab yang disebutkan.
|
| Daftarnya ditulis per domain pada tests/Feature/Sistem/idempoten/*.php, seperti matriks
| otorisasi: PR dua domain tidak pernah menyunting berkas yang sama hanya karena sama-sama
| menambah rute POST.
*/

/** @return array{berkunci: list<string>, tanpa_kunci: array<string, string>} */
function daftarPost(): array
{
    $gabung = ['berkunci' => [], 'tanpa_kunci' => []];

    foreach (glob(__DIR__.'/idempoten/*.php') as $berkas) {
        $domain = require $berkas;
        $gabung['berkunci'] = [...$gabung['berkunci'], ...$domain['berkunci']];
        $gabung['tanpa_kunci'] += $domain['tanpa_kunci'];
    }

    return $gabung;
}

/** @return array<string, list<string>> "POST alamat" => middleware rute itu */
function rutePost(): array
{
    $daftar = [];

    foreach (Route::getRoutes() as $rute) {
        if (str_starts_with($rute->uri(), 'api/v1') && in_array('POST', $rute->methods(), true)) {
            $daftar['POST '.$rute->uri()] = array_values(
                array_filter($rute->gatherMiddleware(), 'is_string'),
            );
        }
    }

    return $daftar;
}

it('mencatat setiap rute POST pada tepat satu daftar', function () {
    $daftar = daftarPost();
    $tercatat = [...$daftar['berkunci'], ...array_keys($daftar['tanpa_kunci'])];
    $rute = array_keys(rutePost());

    $belum = array_values(array_diff($rute, $tercatat));
    $usang = array_values(array_diff($tercatat, $rute));
    $ganda = array_values(array_intersect($daftar['berkunci'], array_keys($daftar['tanpa_kunci'])));

    expect($belum)->toBeEmpty('Rute POST belum diklasifikasi: '.implode(', ', $belum))
        ->and($usang)->toBeEmpty('Tercatat tetapi rutenya tidak ada: '.implode(', ', $usang))
        ->and($ganda)->toBeEmpty('Tercatat pada kedua daftar: '.implode(', ', $ganda));
});

it('memasang idempoten pada setiap rute berkunci, di belakang kunci sesi', function () {
    $salah = [];

    foreach (daftarPost()['berkunci'] as $nama) {
        $middleware = rutePost()[$nama] ?? [];
        $auth = array_search('auth:sesi', $middleware, true);
        $idempoten = array_search('idempoten', $middleware, true);

        // Idempoten mencatat kunci atas nama pengguna, maka harus berjalan setelah auth:sesi.
        if ($auth === false || $idempoten === false || $idempoten < $auth) {
            $salah[] = $nama;
        }
    }

    expect($salah)->toBeEmpty('Tanpa idempoten atau urutannya salah: '.implode(', ', $salah));
});

it('tidak memasang idempoten pada rute yang dinyatakan tidak memerlukannya', function () {
    $salah = array_keys(array_filter(
        array_intersect_key(rutePost(), daftarPost()['tanpa_kunci']),
        fn (array $middleware) => in_array('idempoten', $middleware, true),
    ));

    expect($salah)->toBeEmpty('Tanpa kunci tetapi memasang idempoten: '.implode(', ', $salah));
});

it('menuliskan alasan untuk setiap rute tanpa kunci', function () {
    foreach (daftarPost()['tanpa_kunci'] as $nama => $alasan) {
        expect(mb_strlen($alasan))->toBeGreaterThan(20, "Alasan {$nama} terlalu singkat.");
    }
});
