<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Pesanan\PelakuPesanan;
use App\Http\Requests\AlasanBatalRequest;
use App\Http\Requests\DaftarPesananTokoRequest;
use App\Http\Requests\KirimPesananRequest;
use App\Http\Resources\PesananTokoResource;
use App\Models\Pesanan;
use App\Models\Toko;
use App\Services\LayananPesanan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pesanan yang masuk ke toko milik pengguna yang sedang masuk.
 * Controller ini tidak mengubah apa pun sendiri. Ia melakukan dua hal: mencari pesanan MELALUI
 * toko milik pengguna, lalu menyerahkannya kepada LayananPesanan.
 */
final class PesananTokoController extends Controller
{
    public function __construct(private readonly LayananPesanan $layanan) {}

    /** GET /toko-saya/pesanan: terbaru lebih dulu, dapat disaring menurut status. */
    public function index(DaftarPesananTokoRequest $request): JsonResponse
    {
        $halaman = Pesanan::query()
            ->milikToko($this->tokoSaya($request))
            ->with('butir')
            ->when(
                $request->validated('status'),
                fn (Builder $kueri, string $status) => $kueri->where('status', $status),
            )
            ->orderByDesc('id')
            ->paginate(config('lapak.katalog.per_halaman_bawaan'), ['*'], 'halaman');

        return $this->berhalaman($halaman, PesananTokoResource::class);
    }

    /** GET /toko-saya/pesanan/{nomor} */
    public function show(Request $request, string $nomor): JsonResponse
    {
        return $this->jawab($this->pesananToko($request, $nomor));
    }

    /** POST /toko-saya/pesanan/{nomor}/kemas */
    public function kemas(Request $request, string $nomor): JsonResponse
    {
        return $this->jawab($this->layanan->kemas($this->pesananToko($request, $nomor)));
    }

    /** POST /toko-saya/pesanan/{nomor}/kirim */
    public function kirim(KirimPesananRequest $request, string $nomor): JsonResponse
    {
        return $this->jawab($this->layanan->kirim(
            $this->pesananToko($request, $nomor),
            $request->validated('nomor_resi'),
        ));
    }

    /**
     * POST /toko-saya/pesanan/{nomor}/batal: stok kembali dan dana kembali kepada pembeli.
     * Wajib Idempotency-Key (LP-11): pembatalan memindahkan uang.
     */
    public function batal(AlasanBatalRequest $request, string $nomor): JsonResponse
    {
        return $this->jawab($this->layanan->batalkan(
            $this->pesananToko($request, $nomor),
            PelakuPesanan::Penjual,
            $request->user(),
            $request->validated('alasan'),
        ));
    }

    private function tokoSaya(Request $request): Toko
    {
        return Toko::query()->milik($request->user())->firstOrFail();
    }

    private function pesananToko(Request $request, string $nomor): Pesanan
    {
        return Pesanan::query()
            ->milikToko($this->tokoSaya($request))
            ->where('nomor', $nomor)
            ->firstOrFail();
    }

    private function jawab(Pesanan $pesanan): JsonResponse
    {
        return $this->sukses(PesananTokoResource::make($pesanan->load('butir')));
    }
}
