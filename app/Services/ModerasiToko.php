<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NamaPeran;
use App\Enums\StatusToko;
use App\Exceptions\TransisiTokoTidakSah;
use App\Models\Pengguna;
use App\Models\Toko;
use Illuminate\Support\Facades\DB;

/**
 * Keputusan admin dan alur moderasi toko (LP-3).
 *
 * menunggu --setujui--> aktif --tangguhkan--> ditangguhkan --setujui--> aktif
 * menunggu --tolak----> ditolak --ajukanUlang--> menunggu
 *
 * Peran penjual lahir di sini dan hanya di sini: saat sebuah toko disetujui.
 */
final class ModerasiToko
{
    public function __construct(private readonly PengelolaPeran $peran) {}

    public function setujui(Toko $toko, Pengguna $admin): Toko
    {
        // Status toko dan peran pemiliknya berubah bersama atau tidak sama sekali.
        return DB::transaction(function () use ($toko, $admin): Toko {
            $asal = [StatusToko::Menunggu, StatusToko::Ditangguhkan];
            $this->pindahkan($toko, $asal, StatusToko::Aktif, $admin);

            // Pemilik toko yang dipulihkan sudah memegang peran ini: beri() mengembalikan false
            // dan sesinya tidak diganggu. Pemilik toko baru kehilangan sesinya dan masuk ulang.
            $this->peran->beri($toko->pemilik()->firstOrFail(), NamaPeran::Penjual, $admin);

            return $toko->refresh();
        });
    }

    /**
     * Menolak pengajuan toko yang masih menunggu dengan alasan wajib.
     * Pemilik tetap pembeli (peran penjual tidak diberikan) dan dapat mengajukan ulang.
     */
    public function tolak(Toko $toko, Pengguna $admin, string $alasan): Toko
    {
        $this->pindahkan($toko, [StatusToko::Menunggu], StatusToko::Ditolak, $admin, $alasan);

        return $toko->refresh();
    }

    /**
     * Mengembalikan toko yang ditolak ke antrean menunggu setelah pemilik membenahi datanya.
     */
    public function ajukanUlang(Toko $toko): Toko
    {
        $berubah = Toko::query()
            ->whereKey($toko->id)
            ->where('status', StatusToko::Ditolak)
            ->update([
                'status' => StatusToko::Menunggu,
                'catatan_moderasi' => null,
            ]);

        if ($berubah === 0) {
            throw new TransisiTokoTidakSah(StatusToko::Menunggu);
        }

        return $toko->refresh();
    }

    /**
     * Peran penjual TIDAK dicabut: pemilik tetap perlu melihat dompet dan membenahi produknya.
     * Yang menutup tokonya dari publik adalah status toko (LP-4), bukan hilangnya peran.
     */
    public function tangguhkan(Toko $toko, Pengguna $admin, string $alasan): Toko
    {
        $this->pindahkan($toko, [StatusToko::Aktif], StatusToko::Ditangguhkan, $admin, $alasan);

        return $toko->refresh();
    }

    /**
     * @param  list<StatusToko>  $dari
     */
    private function pindahkan(
        Toko $toko,
        array $dari,
        StatusToko $ke,
        Pengguna $admin,
        ?string $catatan = null,
    ): void {
        // Syarat status asal ikut di dalam UPDATE, tidak diperiksa lebih dulu dengan SELECT.
        // Bila dua admin memutuskan toko yang sama bersamaan, hanya satu UPDATE yang mengenai baris.
        $berubah = Toko::query()
            ->whereKey($toko->id)
            ->whereIn('status', $dari)
            ->update([
                'status' => $ke,
                'dimoderasi_oleh' => $admin->id,
                'dimoderasi_pada' => now(),
                'catatan_moderasi' => $catatan,
            ]);

        if ($berubah === 0) {
            throw new TransisiTokoTidakSah($ke);
        }
    }
}
