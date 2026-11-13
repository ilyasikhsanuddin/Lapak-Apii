<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatusProduk;
use App\Http\Requests\ProdukSayaRequest;
use App\Http\Resources\ProdukSayaResource;
use App\Models\Produk;
use App\Models\Toko;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ProdukSayaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $toko = Toko::query()->milik($request->user())->firstOrFail();

        $produk = Produk::query()
            ->where('toko_id', $toko->id)
            ->with(['kategori', 'toko'])
            ->orderByDesc('id')
            ->get();

        return $this->sukses(ProdukSayaResource::collection($produk));
    }

    public function store(ProdukSayaRequest $request): JsonResponse
    {
        $toko = Toko::query()->milik($request->user())->firstOrFail();
        $data = $request->validated();

        $produk = new Produk([
            'kategori_id' => $data['kategori_id'],
            'nama' => $data['nama'],
            'slug' => $this->buatSlugUnik($data['nama']),
            'deskripsi' => $data['deskripsi'] ?? null,
            'harga_rupiah' => $data['harga_rupiah'],
            'stok' => $data['stok'],
            'berat_gram' => $data['berat_gram'] ?? 0,
            'status' => $data['status'] ?? StatusProduk::Draf->value,
        ]);
        $produk->toko_id = $toko->id;
        $produk->save();
        $produk->load(['kategori', 'toko']);

        return $this->sukses(ProdukSayaResource::make($produk), 201);
    }

    public function update(ProdukSayaRequest $request, Produk $produk): JsonResponse
    {
        $produk->load('toko');
        abort_unless($produk->dimilikiOleh($request->user()), 404);

        $data = $request->validated();
        if (isset($data['nama']) && $data['nama'] !== $produk->nama) {
            $data['slug'] = $this->buatSlugUnik($data['nama']);
        }

        $produk->fill($data);
        $produk->save();
        $produk->load('kategori');

        return $this->sukses(ProdukSayaResource::make($produk));
    }

    public function destroy(Request $request, Produk $produk): JsonResponse
    {
        $produk->load('toko');
        abort_unless($produk->dimilikiOleh($request->user()), 404);

        $produk->delete();

        return $this->sukses(['terhapus' => true]);
    }

    private function buatSlugUnik(string $nama): string
    {
        $dasar = Str::slug($nama);
        $slug = $dasar;
        $urutan = 2;

        while (Produk::query()->where('slug', $slug)->exists()) {
            $slug = "{$dasar}-{$urutan}";
            $urutan++;
        }

        return $slug;
    }
}
