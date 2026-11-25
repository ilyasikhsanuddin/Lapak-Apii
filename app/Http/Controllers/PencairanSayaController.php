<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Uang;
use App\Enums\StatusPencairan;
use App\Http\Requests\PencairanRequest;
use App\Http\Resources\PencairanResource;
use App\Models\Pencairan;
use App\Services\LayananPencairan;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PencairanSayaController extends Controller
{
    /**
     * Daftar pengajuan pencairan milik pengguna yang sedang masuk.
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $pencairans = Pencairan::query()
            ->where('pengaju_id', $user->id)
            ->where('status', StatusPencairan::Menunggu)
            ->get();

        return response()->json(PencairanResource::collection($pencairans));
    }

    /**
     * Ajukan pencairan baru.
     */
    public function store(PencairanRequest $request, LayananPencairan $layanan): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validated();

        $layanan->ajukan(
            $user,
            Uang::dari($data['jumlah_rupiah']),
            $data['rekening_tujuan'],
        );

        return response()->json(['message' => 'Pencairan diajukan'], 201);
    }
}
