<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Audit\HasilPemeriksaan;
use App\Domain\Audit\RantaiHash;
use App\Models\LogAudit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;

/**
 * Membaca seluruh log audit dan menghitung ulang rantainya (LP-17). Hanya membaca.
 *
 * Empat hal diperiksa:
 *   1. setiap baris menyambung ke hash baris sebelumnya,
 *   2. hash setiap baris cocok dengan isinya,
 *   3. ujung dan panjang rantai sama dengan catatan pada kepala rantai, dan
 *   4. bila diberi jangkar, rantai masih melewati titik yang dicatat jangkar itu.
 */
final class PemeriksaRantaiAudit
{
    private const POLA_JANGKAR = '/\A(0|[1-9]\d*):([0-9a-f]{64})\z/';

    /**
     * @param  string|null  $jangkar  "jumlah:hash" dari pemeriksaan terdahulu, disimpan di luar
     */
    public function periksa(?string $jangkar = null): HasilPemeriksaan
    {
        [$barisJangkar, $hashJangkar] = $this->urai($jangkar);

        // Satu transaksi supaya kepala dan seluruh barisnya dibaca dari potret yang sama.
        // Tanpa ini, tindakan yang dicatat di tengah pemeriksaan tampak sebagai ekor liar.
        return DB::transaction(function () use ($barisJangkar, $hashJangkar): HasilPemeriksaan {
            $kepala = DB::table('rantai_audit')->where('id', 1)->first();
            $sebelumnya = RantaiHash::awal();
            $jumlah = 0;
            $temuan = [];

            if ($barisJangkar === 0 && $hashJangkar !== $sebelumnya) {
                $temuan[] = 'Jangkar kosong tidak cocok dengan hash awal rantai.';
            }

            // Urutan baris, bukan nomor id: nomor urut otomatis dapat berlubang
            // karena transaksi yang batal tetap menghabiskan satu nomor.
            foreach (LogAudit::query()->lazyById(500) as $log) {
                $jumlah++;

                if ($log->hash_sebelumnya !== $sebelumnya) {
                    $temuan[] = "Baris {$log->id} tidak menyambung ke baris sebelumnya: "
                        .'ada baris yang dihapus, disisipkan, atau ditukar.';
                }

                try {
                    $hitung = RantaiHash::hitung($log->hash_sebelumnya, $log->isiTerlindung());
                } catch (InvalidArgumentException|JsonException) {
                    $hitung = null;
                }

                if ($hitung !== $log->hash) {
                    $temuan[] = "Baris {$log->id} tidak cocok dengan hash-nya: isinya diubah.";
                }

                if ($jumlah === $barisJangkar && $log->hash !== $hashJangkar) {
                    $temuan[] = "Baris ke-{$jumlah} tidak cocok dengan jangkar: "
                        .'rantai dihitung ulang setelah jangkar itu dicatat.';
                }

                $sebelumnya = $log->hash;
            }

            if ($kepala === null) {
                $temuan[] = 'Kepala rantai tidak ada.';
            } elseif ($kepala->hash_terakhir !== $sebelumnya || (int) $kepala->jumlah !== $jumlah) {
                $temuan[] = "Kepala rantai mencatat {$kepala->jumlah} baris, log berisi {$jumlah}: "
                    .'ekor rantai dipotong, atau ada baris yang ditulis di luar PencatatAudit.';
            }

            if ($barisJangkar !== null && $jumlah < $barisJangkar) {
                $temuan[] = "Jangkar mencatat {$barisJangkar} baris, log hanya berisi {$jumlah}.";
            }

            return new HasilPemeriksaan($jumlah, $sebelumnya, $temuan);
        });
    }

    /** @return array{0: int|null, 1: string|null} */
    private function urai(?string $jangkar): array
    {
        if ($jangkar === null) {
            return [null, null];
        }

        if (preg_match(self::POLA_JANGKAR, $jangkar, $bagian) !== 1
            || filter_var($bagian[1], FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('Jangkar harus berbentuk <jumlah>:<hash 64 huruf>.');
        }

        return [(int) $bagian[1], $bagian[2]];
    }
}
