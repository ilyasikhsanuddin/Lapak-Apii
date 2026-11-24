<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DaftarAuditRequest;
use App\Http\Resources\LogAuditResource;
use App\Models\LogAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Pintu auditor atas log audit (LP-17). Hanya GET: log bertambah lewat tindakan yang
 * dicatatnya, tidak pernah lewat pintu ini.
 */
final class AuditController extends Controller
{
    /** GET /audit: terbaru lebih dulu, dapat disaring menurut peristiwa, sasaran, dan pelaku. */
    public function index(DaftarAuditRequest $request): JsonResponse
    {
        $saring = $request->validated();

        $halaman = LogAudit::query()
            ->with('pelaku')
            ->when($saring['peristiwa'] ?? null, fn (Builder $k, $v) => $k->where('peristiwa', $v))
            ->when($saring['sasaran'] ?? null, fn (Builder $k, $v) => $k->where('sasaran', $v))
            ->when($saring['pelaku_id'] ?? null, fn (Builder $k, $v) => $k->where('pelaku_id', $v))
            ->orderByDesc('id')
            ->paginate(config('lapak.katalog.per_halaman_bawaan'), ['*'], 'halaman');

        return $this->berhalaman($halaman, LogAuditResource::class);
    }

    /**
     * GET /audit/rantai: ujung dan panjang rantai saat ini. Murah, karena hanya membaca satu
     * baris. Pemeriksaan seluruh rantai dijalankan lewat `php artisan lapak:verifikasi-audit`.
     */
    public function rantai(): JsonResponse
    {
        $kepala = DB::table('rantai_audit')->where('id', 1)->firstOrFail();

        return $this->sukses([
            'jumlah' => (int) $kepala->jumlah,
            'hash_terakhir' => $kepala->hash_terakhir,
            'jangkar' => "{$kepala->jumlah}:{$kepala->hash_terakhir}",
        ]);
    }
}
