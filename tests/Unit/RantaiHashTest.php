<?php

use App\Domain\Audit\RantaiHash;

it('menghasilkan hash yang sama untuk isi yang sama walau urutan kuncinya berbeda', function () {
    $a = ['peristiwa' => 'toko.disetujui', 'rincian' => ['b' => 1, 'a' => 2]];
    $b = ['rincian' => ['a' => 2, 'b' => 1], 'peristiwa' => 'toko.disetujui'];

    expect(RantaiHash::hitung(RantaiHash::awal(), $a))
        ->toBe(RantaiHash::hitung(RantaiHash::awal(), $b));
});

it('menghasilkan hash berbeda bila satu huruf pada isi berubah', function () {
    $asli = RantaiHash::hitung(RantaiHash::awal(), ['sasaran' => 'toko:4']);
    $ubah = RantaiHash::hitung(RantaiHash::awal(), ['sasaran' => 'toko:5']);

    expect($asli)->not->toBe($ubah);
});

it('menghasilkan hash berbeda bila hash sebelumnya berbeda', function () {
    $isi = ['sasaran' => 'toko:4'];

    expect(RantaiHash::hitung(RantaiHash::awal(), $isi))
        ->not->toBe(RantaiHash::hitung(str_repeat('1', 64), $isi));
});

it('menjaga urutan larik dan hanya mengurutkan kunci objek', function () {
    expect(RantaiHash::kanonik(['x' => [3, 1, 2]]))->toBe('{"x":[3,1,2]}');
});

it('tidak mengubah huruf Unicode dan garis miring menjadi escape', function () {
    expect(RantaiHash::kanonik(['catatan' => 'Sesuai mutasi — lunas', 'rute' => 'a/b']))
        ->toBe('{"catatan":"Sesuai mutasi — lunas","rute":"a/b"}');
});

it('menolak pecahan, di tingkat mana pun', function () {
    expect(fn () => RantaiHash::kanonik(['jumlah' => 0.1]))->toThrow(InvalidArgumentException::class);
    expect(fn () => RantaiHash::kanonik(['dalam' => ['jumlah' => 1.5]]))->toThrow(InvalidArgumentException::class);
});

it('menolak objek', function () {
    expect(fn () => RantaiHash::kanonik(['x' => new stdClass]))->toThrow(InvalidArgumentException::class);
});

it('memakai 64 nol sebagai hash awal', function () {
    expect(RantaiHash::awal())->toBe(str_repeat('0', 64))->toHaveLength(64);
});
