<?php

declare(strict_types=1);

use App\Domain\Pesanan\PenomorPesanan;

it('membuat nomor pesanan dengan format yang benar', function () {
    $nomor = PenomorPesanan::untuk(
        123,
        new DateTimeImmutable('2026-10-27 03:15:00 UTC'),
    );

    expect($nomor)->toBe('LPK-20261027-000123')
        ->and($nomor)->toMatch(PenomorPesanan::POLA);
});

it('menggunakan tanggal WIB bukan tanggal UTC', function () {
    $nomor = PenomorPesanan::untuk(
        7,
        new DateTimeImmutable('2026-10-27 23:30:00 UTC'),
    );

    expect($nomor)->toBe('LPK-20261028-000007');
});

it('tidak memotong ID pesanan yang lebih dari enam digit', function () {
    $nomor = PenomorPesanan::untuk(
        1234567,
        new DateTimeImmutable('2026-10-27 03:15:00 UTC'),
    );

    expect($nomor)->toBe('LPK-20261027-1234567')
        ->and($nomor)->toMatch(PenomorPesanan::POLA);
});
