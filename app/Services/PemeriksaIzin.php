<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NamaIzin;
use App\Models\Pengguna;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat pertanyaan "pengguna ini punya izin itu?" dijawab (LP-2).
 * Jawabannya selalu dibaca dari basis data: gabungan izin dari seluruh peran yang dipegang.
 */
final class PemeriksaIzin
{
    public function punya(Pengguna $pengguna, NamaIzin $izin): bool
    {
        return in_array($izin->value, $this->daftar($pengguna), true);
    }

    /**
     * @return list<string> nama izin, urut abjad, tanpa kembaran
     */
    public function daftar(Pengguna $pengguna): array
    {
        return DB::table('izin')
            ->join('peran_izin', 'peran_izin.izin_id', '=', 'izin.id')
            ->join('pengguna_peran', 'pengguna_peran.peran_id', '=', 'peran_izin.peran_id')
            ->where('pengguna_peran.pengguna_id', $pengguna->id)
            ->distinct()
            ->orderBy('izin.nama')
            ->pluck('izin.nama')
            ->all();
    }

    /**
     * Dipanggil setiap kali peran seorang pengguna berubah.
     * Belum melakukan apa pun karena belum ada yang diingat; titik kait ini disepakati sejak kontrak
     * supaya pemanggilnya sudah benar ketika cache izin ditambahkan.
     */
    public function lupakan(Pengguna $pengguna): void
    {
        //
    }
}
