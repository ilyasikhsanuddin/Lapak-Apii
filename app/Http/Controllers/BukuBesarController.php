<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\DompetBukuBesarResource;
use App\Http\Resources\MutasiBukuBesarResource;
use App\Models\Dompet;
use App\Models\MutasiSaldo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class BukuBesarController extends Controller
{
    /** GET /buku-besar/dompet: seluruh dompet beserta saldonya. */
    public function index(): JsonResponse
    {
        $dompet = Dompet::query()
            ->with('pemilik')
            ->orderBy('id')
            ->get();

        $jumlah = (int) $dompet->sum('saldo_rupiah');

        return $this->sukses(DompetBukuBesarResource::collection($dompet), 200, [
            'jumlah_dompet' => $dompet->count(),
            // LP-13: uang hanya berpindah, sehingga jumlah seluruh saldo selalu nol.
            'jumlah_seluruh_saldo' => $jumlah,
            'seimbang' => $jumlah === 0,
        ]);
    }

    /** GET /buku-besar/dompet/{id}/mutasi: riwayat satu dompet, terbaru lebih dulu. */
    public function mutasi(int $id): JsonResponse
    {
        $halaman = Dompet::query()
            ->findOrFail($id)
            ->mutasi()
            ->orderByDesc('id')
            ->paginate(config('lapak.katalog.per_halaman_bawaan'), ['*'], 'halaman');

        return $this->berhalaman($halaman, MutasiBukuBesarResource::class);
    }

    /** GET /buku-besar/kelompok/{kelompok}: menelusuri pasangan mutasi satu pemindahan (LP-13). */
    public function kelompok(string $kelompok): JsonResponse
    {
        // Nilai bukan UUID langsung dijawab 404 tanpa menyentuh basis data.
        if (! Str::isUuid($kelompok)) {
            abort(404);
        }

        $mutasi = MutasiSaldo::query()
            ->with('dompet')
            ->where('kelompok', $kelompok)
            ->orderBy('id')
            ->get();

        if ($mutasi->isEmpty()) {
            abort(404);
        }

        return $this->sukses(MutasiBukuBesarResource::collection($mutasi), 200, [
            'kelompok' => $kelompok,
            'jumlah_baris' => $mutasi->count(),
            'seimbang' => (int) $mutasi->sum('jumlah_rupiah') === 0,
        ]);
    }
}
