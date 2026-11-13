<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatusToko;
use App\Exceptions\SudahPunyaToko;
use App\Http\Requests\TokoSayaRequest;
use App\Http\Resources\TokoSayaResource;
use App\Models\Toko;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TokoSayaController extends Controller
{
    /** Tidak ada parameter toko pada alamatnya: toko ditentukan sesi, bukan klien. */
    public function show(Request $request): JsonResponse
    {
        $toko = Toko::query()->milik($request->user())->withCount('produk')->firstOrFail();

        return $this->sukses(TokoSayaResource::make($toko));
    }

    public function store(TokoSayaRequest $request): JsonResponse
    {
        $pengguna = $request->user();

        if (Toko::query()->milik($pengguna)->exists()) {
            throw new SudahPunyaToko;
        }

        $data = $request->validated();

        $toko = new Toko([
            'nama' => $data['nama'],
            'slug' => $this->buatSlugUnik($data['nama']),
            'kota' => $data['kota'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'ongkir_rupiah' => $data['ongkir_rupiah'] ?? 0,
        ]);
        $toko->pemilik_id = $pengguna->id;
        // LP-4: toko baru dimulai dari status menunggu sampai disetujui admin.
        $toko->status = StatusToko::Menunggu;
        $toko->save();

        return $this->sukses(TokoSayaResource::make($toko), 201);
    }

    public function update(TokoSayaRequest $request): JsonResponse
    {
        $toko = Toko::query()->milik($request->user())->firstOrFail();

        $data = $request->validated();
        if (isset($data['nama']) && $data['nama'] !== $toko->nama) {
            $data['slug'] = $this->buatSlugUnik($data['nama']);
        }

        $toko->fill($data);
        $toko->save();

        return $this->sukses(TokoSayaResource::make($toko));
    }

    private function buatSlugUnik(string $nama): string
    {
        $dasar = Str::slug($nama);
        $slug = $dasar;
        $urutan = 2;

        while (Toko::query()->where('slug', $slug)->exists()) {
            $slug = "{$dasar}-{$urutan}";
            $urutan++;
        }

        return $slug;
    }
}
