<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UbahKataSandiRequest;
use App\Http\Resources\SesiResource;
use App\Models\Sesi;
use App\Services\PenerbitSesi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class UbahKataSandiController extends Controller
{
    public function __construct(private readonly PenerbitSesi $penerbit) {}

    /**
     * PUT /saya/kata-sandi. Berada di belakang sesi.segar (LP-15).
     * Kata sandi berganti, seluruh perangkat lain diusir, dan kunci perangkat ini dirotasi.
     * Ketiganya satu transaksi: tidak ada keadaan setengah jadi.
     */
    public function __invoke(UbahKataSandiRequest $request): JsonResponse
    {
        $pengguna = $request->user();
        $sesi = Sesi::dariPermintaan($request);

        $kunciBaru = DB::transaction(function () use ($request, $pengguna, $sesi): string {
            // Cast hashed pada model yang menghasilkan hash. Jangan memanggil Hash::make() lagi.
            $pengguna->forceFill(['kata_sandi' => $request->validated('kata_sandi_baru')])->save();

            $this->penerbit->cabutSemua($pengguna, kecuali: $sesi);

            return $this->penerbit->rotasi($sesi);
        });

        return $this->sukses([
            'kunci' => $kunciBaru,
            'sesi' => SesiResource::saatIni($sesi),
        ]);
    }
}
