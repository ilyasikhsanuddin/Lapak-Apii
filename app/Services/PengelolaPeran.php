<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NamaPeran;
use App\Enums\PeristiwaAudit;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Satu-satunya pintu perubahan peran, seperti BukuBesar bagi saldo.
 *
 * Setiap perubahan peran diikuti tiga hal yang tidak boleh terlewat:
 * 1. ingatan izin pengguna itu dilupakan,
 * 2. seluruh sesinya dicabut, sehingga ia masuk ulang dan menerima kunci baru, dan
 * 3. perubahannya dicatat pada log audit (LP-17).
 *
 * Butir 2 adalah bentuk "ganti kunci saat kedudukan sesi berubah" untuk keadaan ketika pemilik
 * sesi tidak hadir menerima kunci pengganti: yang mengubah perannya orang lain.
 */
final class PengelolaPeran
{
    public function __construct(
        private readonly PemeriksaIzin $izin,
        private readonly PenerbitSesi $sesi,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  Pengguna|null  $oleh  null bila pemberinya sistem: registrasi, seeder, baris perintah
     * @return bool false bila pengguna sudah memegang peran itu, sehingga tidak ada yang berubah
     */
    public function beri(Pengguna $pengguna, NamaPeran $peran, ?Pengguna $oleh = null): bool
    {
        $idPeran = $this->idPeran($peran);

        return DB::transaction(function () use ($pengguna, $peran, $oleh, $idPeran): bool {
            if ($pengguna->peran()->where('peran.id', $idPeran)->exists()) {
                return false;
            }

            try {
                $pengguna->peran()->attach($idPeran, ['diberikan_oleh' => $oleh?->id]);
            } catch (UniqueConstraintViolationException) {
                // Dua pemberian bersamaan: kunci utama pada pengguna_peran menolak yang kedua.
                return false;
            }

            $this->setelahBerubah($pengguna, PeristiwaAudit::PeranDiberikan, $peran, $oleh);

            return true;
        });
    }

    /**
     * @return bool false bila pengguna memang tidak memegang peran itu
     */
    public function cabut(Pengguna $pengguna, NamaPeran $peran, ?Pengguna $oleh = null): bool
    {
        $idPeran = $this->idPeran($peran);

        return DB::transaction(function () use ($pengguna, $peran, $oleh, $idPeran): bool {
            if ($pengguna->peran()->detach($idPeran) === 0) {
                return false;
            }

            $this->setelahBerubah($pengguna, PeristiwaAudit::PeranDicabut, $peran, $oleh);

            return true;
        });
    }

    /**
     * @return list<string> nama peran yang dipegang, urut abjad
     */
    public function daftar(Pengguna $pengguna): array
    {
        return $pengguna->peran()->orderBy('peran.nama')->pluck('peran.nama')->all();
    }

    private function setelahBerubah(
        Pengguna $pengguna,
        PeristiwaAudit $peristiwa,
        NamaPeran $peran,
        ?Pengguna $oleh,
    ): void {
        $this->izin->lupakan($pengguna);
        $this->sesi->cabutSemua($pengguna);

        // Jejak ditulis paling akhir, dalam transaksi yang sama dengan perubahan perannya.
        $this->audit->catat($peristiwa, $oleh, "pengguna:{$pengguna->id}", [
            'peran' => $peran->value,
        ]);
    }

    private function idPeran(NamaPeran $peran): int
    {
        $id = Peran::query()->where('nama', $peran->value)->value('id');

        // Bukan 404: yang hilang adalah data acuan, bukan sesuatu yang dicari klien.
        if ($id === null) {
            throw new LogicException(
                "Peran {$peran->value} belum ada di basis data. Jalankan PeranIzinSeeder.",
            );
        }

        return (int) $id;
    }
}
