<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Belanja\PenghitungBelanja;
use App\Http\Requests\SimulasiBelanjaRequest;
use App\Http\Resources\RincianTokoResource;
use App\Services\PenyusunBelanja;

final class SimulasiBelanjaController extends Controller
{
    public function __invoke(
        SimulasiBelanjaRequest $request,
        PenyusunBelanja $penyusun,
    ) {
        $rincian = $penyusun->susun($request->butir());

        $total = PenghitungBelanja::totalBayar($rincian);

        return $this->sukses([
            'pesanan' => RincianTokoResource::collection($rincian),
            'total_bayar' => [
                'rupiah' => $total->rupiah,
                'tampilan' => $total->format(),
            ],
            'catatan' => 'Simulasi tidak mengunci harga maupun stok. Kepastian baru ada saat checkout.',
        ]);
    }
}