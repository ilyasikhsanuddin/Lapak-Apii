<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Sesi\KunciSesi;
use App\Models\Pengguna;
use App\Models\Sesi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Satu-satunya tempat kunci sesi dibuat, diganti, dan dicabut.
 */
final class PenerbitSesi
{
    /**
     * @return array{0: Sesi, 1: string} sesi dan kunci utuhnya. Kunci utuh hanya ada di sini,
     *                                   saat ini; setelah fungsi ini selesai tidak dapat diperoleh lagi.
     */
    public function terbitkan(
        Pengguna $pengguna,
        string $namaPerangkat,
        ?string $ip = null,
        ?string $agenPengguna = null,
    ): array {
        $kunci = KunciSesi::baru((string) Str::ulid());

        $sesi = DB::transaction(function () use ($pengguna, $namaPerangkat, $ip, $agenPengguna, $kunci): Sesi {
            $this->sisihkanTempat($pengguna);

            $sesi = new Sesi;
            $sesi->forceFill([
                'pengenal' => $kunci->pengenal,
                'pengguna_id' => $pengguna->id,
                'hash_kunci' => $kunci->hash(),
                'nama_perangkat' => Str::limit($namaPerangkat, 57),
                'ip' => $ip,
                'agen_pengguna' => $agenPengguna === null ? null : Str::limit($agenPengguna, 252),
                // Login adalah pembuktian kata sandi, jadi sesi baru langsung segar (LP-15).
                'dikonfirmasi_pada' => now(),
                'terakhir_dipakai_pada' => now(),
                'kedaluwarsa_pada' => now()->addDays(config('lapak.sesi.masa_hidup_hari')),
            ])->save();

            return $sesi;
        });

        return [$sesi, $kunci->teks()];
    }

    /**
     * Batas perangkat (LP-14): sesi berlaku per pengguna paling banyak sebanyak
     * config('lapak.sesi.maks_perangkat'). Bila sudah penuh, sesi yang paling lama
     * tidak dipakai dicabut agar sesi baru mendapat tempat.
     */
    private function sisihkanTempat(Pengguna $pengguna): void
    {
        $maks = max(1, (int) config('lapak.sesi.maks_perangkat', 5));

        $aktif = Sesi::query()
            ->berlaku()
            ->where('pengguna_id', $pengguna->id)
            ->orderBy('terakhir_dipakai_pada')
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        // Satu tempat disisakan untuk sesi yang akan dibuat.
        $kelebihan = $aktif->count() - $maks + 1;

        if ($kelebihan > 0) {
            Sesi::query()
                ->whereIn('id', $aktif->take($kelebihan)->pluck('id'))
                ->update(['dicabut_pada' => now()]);
        }
    }

    /**
     * Mengganti rahasia tanpa mengganti sesi. Kunci lama mati saat itu juga.
     * Dipanggil setiap kali kedudukan sesi naik: setelah konfirmasi kata sandi sekarang,
     * setelah perubahan peran pada Sesi 3.
     */
    public function rotasi(Sesi $sesi): string
    {
        $kunci = KunciSesi::baru($sesi->pengenal);
        $sesi->forceFill(['hash_kunci' => $kunci->hash()])->save();

        return $kunci->teks();
    }

    public function tandaiSegar(Sesi $sesi): void
    {
        $sesi->forceFill(['dikonfirmasi_pada' => now()])->save();
    }

    public function cabut(Sesi $sesi): void
    {
        if ($sesi->dicabut_pada === null) {
            $sesi->forceFill(['dicabut_pada' => now()])->save();
        }
    }

    /**
     * Mencabut seluruh sesi berlaku seorang pengguna di semua perangkat,
     * kecuali satu sesi bila disebut (misalnya sesi yang sedang dipakai).
     *
     * @return int jumlah sesi yang dicabut
     */
    public function cabutSemua(Pengguna $pengguna, ?Sesi $kecuali = null): int
    {
        return Sesi::query()
            ->berlaku()
            ->where('pengguna_id', $pengguna->id)
            ->when($kecuali, fn ($kueri) => $kueri->whereKeyNot($kecuali->getKey()))
            ->update(['dicabut_pada' => now()]);
    }
}
