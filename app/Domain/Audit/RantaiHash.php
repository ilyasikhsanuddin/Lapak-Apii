<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use InvalidArgumentException;

/**
 * Rumus rantai hash log audit (LP-17). PHP murni: tidak mengenal basis data maupun HTTP.
 *
 *     hash baris = SHA-256( hash baris sebelumnya + "\n" + isi baris dalam bentuk kanonik )
 *
 * Mengubah satu huruf pada satu baris mengubah hash baris itu, sehingga tidak lagi cocok dengan
 * hash_sebelumnya pada baris berikutnya. Menghapus atau menyisipkan baris memutus rantai dengan
 * cara yang sama.
 */
final class RantaiHash
{
    /** Hash "sebelumnya" bagi baris pertama. */
    public static function awal(): string
    {
        return str_repeat('0', 64);
    }

    /**
     * @param  array<string, mixed>  $isi
     */
    public static function hitung(string $sebelumnya, array $isi): string
    {
        return hash('sha256', $sebelumnya."\n".self::kanonik($isi));
    }

    /**
     * Bentuk kanonik: JSON dengan kunci objek urut abjad di setiap tingkat.
     *
     * Urutan kunci harus ditetapkan di sini, tidak boleh diserahkan kepada basis data: MySQL
     * menyimpan kolom JSON dengan urutan kuncinya sendiri, sehingga isi yang sama dapat kembali
     * dengan urutan berbeda dari saat ditulis.
     *
     * @param  array<int|string, mixed>  $isi
     */
    public static function kanonik(array $isi): string
    {
        return json_encode(
            self::urutkan($isi),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    private static function urutkan(mixed $nilai): mixed
    {
        if (is_float($nilai)) {
            // LP-1 berlaku juga di sini. Pecahan juga tidak memiliki satu bentuk teks yang pasti.
            throw new InvalidArgumentException('Isi log audit tidak boleh memuat pecahan.');
        }

        if (is_object($nilai)) {
            throw new InvalidArgumentException('Isi log audit harus berupa skalar atau larik.');
        }

        if (! is_array($nilai)) {
            return $nilai;
        }

        if (! array_is_list($nilai)) {
            ksort($nilai, SORT_STRING);
        }

        return array_map(self::urutkan(...), $nilai);
    }
}
