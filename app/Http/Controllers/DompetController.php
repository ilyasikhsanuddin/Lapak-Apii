<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Uang;
use App\Enums\JenisMutasi;
use App\Http\Resources\MutasiResource;
use App\Services\BukuBesar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

final class DompetController extends Controller
{
    public function __construct(private readonly BukuBesar $buku) {}

    /**
     * Saldo milik pengguna yang sedang masuk. Alamatnya tidak memuat pengenal dompet:
     * tidak ada angka yang dapat diganti klien untuk mengintip dompet orang lain.
     */
    public function show(Request $request): JsonResponse
    {
        $dompet = $this->buku->cariDompetPenjual($request->user());
        $saldo = Uang::dari($dompet?->saldo_rupiah ?? 0);

        return $this->sukses([
            'saldo' => ['rupiah' => $saldo, 'tampilan' => $saldo->format()],
            'jumlah_mutasi' => $dompet?->mutasi()->count() ?? 0,
        ]);
    }

    /** Rutenya dipasangi sesi.segar: rincian uang lebih peka daripada angka saldo (LP-15). */
    public function mutasi(Request $request): JsonResponse
    {
        $saringan = $request->validate([
            'jenis' => ['sometimes', Rule::enum(JenisMutasi::class)],
            'dari' => [
                'sometimes',
                'date',
                Rule::when($request->filled('sampai'), ['before_or_equal:sampai']),
            ],
            'sampai' => [
                'sometimes',
                'date',
                Rule::when($request->filled('dari'), ['after_or_equal:dari']),
            ],
        ]);

        $kueri = $this->buku->mutasiPenjual($request->user())
            ->when($saringan['jenis'] ?? null, fn (Builder $q, $jenis) => $q->where('jenis', $jenis))
            ->when($saringan['dari'] ?? null, function (Builder $q, string $dari): void {
                $waktu = str_contains($dari, ':')
                    ? Carbon::parse($dari)
                    : Carbon::parse($dari)->startOfDay();
                $q->where('created_at', '>=', $waktu);
            })
            ->when($saringan['sampai'] ?? null, function (Builder $q, string $sampai): void {
                $waktu = str_contains($sampai, ':')
                    ? Carbon::parse($sampai)
                    : Carbon::parse($sampai)->endOfDay();
                $q->where('created_at', '<=', $waktu);
            });

        $halaman = $kueri->paginate(config('lapak.katalog.per_halaman_bawaan'), ['*'], 'halaman');

        return $this->berhalaman($halaman, MutasiResource::class);
    }
}
