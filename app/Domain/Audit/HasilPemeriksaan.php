<?php

declare(strict_types=1);

namespace App\Domain\Audit;

/** Hasil satu pemeriksaan rantai log audit. Tidak tahu apa-apa tentang basis data. */
final readonly class HasilPemeriksaan
{
    /**
     * @param  int  $jumlah  banyak baris yang terbaca
     * @param  string  $hashTerakhir  hash baris terakhir yang terbaca
     * @param  list<string>  $temuan  kalimat yang menyebut baris dan sebabnya; kosong bila utuh
     */
    public function __construct(
        public int $jumlah,
        public string $hashTerakhir,
        public array $temuan,
    ) {}

    public function utuh(): bool
    {
        return $this->temuan === [];
    }

    /** Bentuk yang disimpan di luar basis data, lalu diberikan kembali lewat --jangkar. */
    public function jangkar(): string
    {
        return "{$this->jumlah}:{$this->hashTerakhir}";
    }
}
