<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PenggunaResource;
use App\Models\Pengguna;
use App\Services\PengelolaAkun;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Pintu admin untuk status akun. Rutenya berada di belakang izin akun.tangguhkan dan sesi segar.
 */
final class StatusAkunController extends Controller
{
    public function __construct(private readonly PengelolaAkun $pengelola) {}

    /** POST /admin/pengguna/{id}/tangguhkan: idempoten. */
    public function tangguhkan(int $id): JsonResponse
    {
        $sasaran = $this->sasaran($id);

        $this->pengelola->tangguhkan($sasaran);

        return $this->sukses(PenggunaResource::make($sasaran->load('peran')));
    }

    /** POST /admin/pengguna/{id}/pulihkan: idempoten. */
    public function pulihkan(int $id): JsonResponse
    {
        $sasaran = $this->sasaran($id);

        $this->pengelola->pulihkan($sasaran);

        return $this->sukses(PenggunaResource::make($sasaran->load('peran')));
    }

    private function sasaran(int $id): Pengguna
    {
        $sasaran = Pengguna::query()->findOrFail($id);

        Gate::authorize('ubahStatus', $sasaran);

        return $sasaran;
    }
}
