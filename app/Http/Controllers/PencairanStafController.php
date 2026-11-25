<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatusPencairan;
use App\Http\Requests\PencairanPutusanRequest;
use App\Http\Resources\PencairanStafResource;
use App\Models\Pencairan;
use App\Services\LayananPencairan;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PencairanStafController extends Controller
{
    /**
     * Daftar seluruh pengajuan pencairan yang masih menunggu.
     */
    public function index(): JsonResponse
    {
        $pencairans = Pencairan::query()
            ->where('status', StatusPencairan::Menunggu)
            ->get();

        return response()->json(PencairanStafResource::collection($pencairans));
    }

    /**
     * Setujui pengajuan pencairan.
     */
    public function setujui(string $kode, LayananPencairan $layanan): JsonResponse
    {
        $pencairan = Pencairan::query()->where('kode', $kode)->firstOrFail();
        $layanan->setujui($pencairan, auth()->user());

        return response()->json(['message' => "Pencairan {$kode} disetujui"]);
    }

    /**
     * Tolak pengajuan pencairan.
     */
    public function tolak(string $kode, PencairanPutusanRequest $request, LayananPencairan $layanan): JsonResponse
    {
        $pencairan = Pencairan::query()->where('kode', $kode)->firstOrFail();
        $catatan = $request->validated()['catatan'] ?? '';
        $layanan->tolak($pencairan, auth()->user(), $catatan);

        return response()->json(['message' => "Pencairan {$kode} ditolak"]);
    }
}
