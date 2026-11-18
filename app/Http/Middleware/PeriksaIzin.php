<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\NamaIzin;
use App\Services\PemeriksaIzin;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemakaian pada rute:  ->middleware('izin:produk.kelola')   Pasang SETELAH auth:sesi.
 *
 * Satu rute, satu izin. Bila dua peran membutuhkan pintu yang sama, keduanya diberi izin yang
 * sama pada NamaPeran::izinBawaan(); rute tidak pernah menulis "peran ini atau peran itu".
 *
 * Middleware ini menjawab pertanyaan JENIS tindakan. Pertanyaan "objek yang ini boleh?"
 * dijawab Policy atau kueri berlingkup di dalam controller.
 */
final class PeriksaIzin
{
    public function __construct(private readonly PemeriksaIzin $pemeriksa) {}

    public function handle(Request $request, Closure $next, string $izin): Response
    {
        // NamaIzin::from melempar galat untuk nama yang salah ketik. Rute dengan izin
        // yang tidak dikenal harus gagal keras, bukan diam-diam terbuka atau tertutup.
        if (! $this->pemeriksa->punya($request->user(), NamaIzin::from($izin))) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
