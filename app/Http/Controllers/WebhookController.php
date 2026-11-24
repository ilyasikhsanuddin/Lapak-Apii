<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\GalatDomain;
use App\Services\LayananPembayaran;
use App\Services\TandaTanganGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Controller untuk menangani webhook pembayaran.
 */
final class WebhookController extends Controller
{
    public function __invoke(
        Request $request,
        TandaTanganGateway $tandaTangan,
        LayananPembayaran $pembayaran,
    ): JsonResponse {
        $tanda = $request->header(TandaTanganGateway::HEADER);

        if (! $tandaTangan->sah($tanda, $request->getContent())) {
            throw new GalatDomain('TANDA_TANGAN_SALAH', 'Tanda tangan webhook tidak sah.', 401);
        }

        $data = Validator::make($request->all(), [
            'id_peristiwa' => ['required', 'string', 'max:64'],
            'kode_pembayaran' => ['required', 'string', 'max:30'],
            'jumlah_rupiah' => ['required', 'integer', 'min:1'],
        ])->validate();

        $hasil = $pembayaran->terima(
            $data['kode_pembayaran'], $data['jumlah_rupiah'], $data['id_peristiwa'],
        );

        return $this->sukses(['hasil' => $hasil]);
    }
}
