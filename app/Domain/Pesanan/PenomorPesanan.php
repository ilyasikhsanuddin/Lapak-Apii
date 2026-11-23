<?php

declare(strict_types=1);

namespace App\Domain\Pesanan;

use DateTimeInterface;
use DateTimeZone;

final class PenomorPesanan
{
    public const POLA = '/\ALPK-\d{8}-\d{6,}\z/';

    private const ZONA_BISNIS = 'Asia/Jakarta';

    public static function untuk(int $idPesanan, DateTimeInterface $dibuatPada): string
    {
        $tanggal = \DateTimeImmutable::createFromInterface($dibuatPada)
            ->setTimezone(new DateTimeZone(self::ZONA_BISNIS))
            ->format('Ymd');

        return sprintf('LPK-%s-%06d', $tanggal, $idPesanan);
    }
}
