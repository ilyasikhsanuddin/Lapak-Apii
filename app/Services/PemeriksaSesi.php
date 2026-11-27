<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Sesi\KunciSesi;
use App\Enums\PeristiwaAudit;
use App\Enums\StatusAkun;
use App\Models\Sesi;
use Illuminate\Support\Facades\DB;

/**
 * Menjawab satu pertanyaan pada setiap permintaan: kunci ini milik sesi yang masih berlaku?
 *
 * Seluruh kegagalan menghasilkan null yang sama. Pemanggil tidak dapat, dan tidak perlu,
 * membedakan kunci salah bentuk, kunci tak dikenal, sesi dicabut, atau sesi kedaluwarsa.
 */
final class PemeriksaSesi
{
    public function __construct(
        private readonly PenerbitSesi $penerbit,
        private readonly PencatatAudit $audit,
    ) {}

    public function periksa(?string $teksKunci): ?Sesi
    {
        $kunci = KunciSesi::urai($teksKunci ?? '');
        if ($kunci === null) {
            return null;
        }

        $sesi = Sesi::query()
            ->berlaku()
            ->with('pengguna')
            ->where('pengenal', $kunci->pengenal)
            ->first();
        if ($sesi === null) {
            return null;
        }

        if (! $kunci->cocok($sesi->hash_kunci)) {
            $this->periksaKunciLama($sesi, $kunci);

            return null;
        }

        // Akun yang ditangguhkan kehilangan aksesnya pada permintaan berikutnya,
        // tanpa menunggu kuncinya kedaluwarsa.
        if ($sesi->pengguna->status !== StatusAkun::Aktif) {
            return null;
        }

        $this->sentuh($sesi);

        return $sesi;
    }

    /**
     * Kunci yang sudah digantikan rotasi muncul lagi. Pemilik sah sudah memegang kunci baru,
     * maka ada dua kemungkinan: jawaban rotasi tidak sampai kepadanya, atau kunci lama pernah
     * disalin orang lain. Server tidak dapat membedakan keduanya dan memilih yang aman: sesi
     * ini dicabut, dan kejadiannya dicatat. Jawaban kepada pemanggil tetap 401 yang sama.
     */
    private function periksaKunciLama(Sesi $sesi, KunciSesi $kunci): void
    {
        if ($sesi->hash_kunci_lama === null || ! $kunci->cocok($sesi->hash_kunci_lama)) {
            return;
        }

        DB::transaction(function () use ($sesi): void {
            $this->penerbit->cabut($sesi);

            $this->audit->catat(
                PeristiwaAudit::KunciLamaDipakai,
                $sesi->pengguna,
                "sesi:{$sesi->pengenal}",
                ['perangkat' => $sesi->nama_perangkat],
            );
        });
    }

    /** Mencatat pemakaian paling sering satu kali per jeda, agar tidak menulis pada tiap permintaan. */
    private function sentuh(Sesi $sesi): void
    {
        $batas = now()->subSeconds(config('lapak.sesi.jeda_sentuh_detik'));

        if ($sesi->terakhir_dipakai_pada->lt($batas)) {
            $sesi->forceFill(['terakhir_dipakai_pada' => now()])->save();
        }
    }
}