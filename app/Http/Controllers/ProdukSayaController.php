<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PeristiwaAudit;
use App\Enums\StatusProduk;
use App\Http\Requests\DaftarProdukSayaRequest;
use App\Http\Requests\PenyesuaianStokRequest;
use App\Http\Requests\ProdukSayaRequest;
use App\Http\Resources\ProdukSayaResource;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\PencatatAudit;
use App\Services\PenjagaStok;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProdukSayaController extends Controller
{
    public function index(DaftarProdukSayaRequest $request): JsonResponse
    {
        $toko = Toko::query()->milik($request->user())->firstOrFail();
        $saringan = $request->validated();

        $produk = Produk::query()
            ->where('toko_id', $toko->id)
            ->when($saringan['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($saringan['cari'] ?? null, fn (Builder $q, string $kata) => $q->whereRaw(
                "produk.nama like ? escape '!'",
                ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $kata).'%'],
            ))
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

    public function stok(
        PenyesuaianStokRequest $request,
        PenjagaStok $penjaga,
        PencatatAudit $audit,
        int $id,
    ): JsonResponse {
        $produk = Produk::query()->with('toko')->findOrFail($id);
        abort_unless($produk->dimilikiOleh($request->user()), 404);

        $selisih = (int) $request->validated('selisih');

        $diperbarui = DB::transaction(function () use ($request, $penjaga, $audit, $produk, $selisih): Produk {
            $terkini = $penjaga->sesuaikan($produk->id, $selisih);

            $audit->catat(PeristiwaAudit::StokDisesuaikan, $request->user(), "produk:{$produk->id}", [
                'selisih' => $selisih,
                'stok_setelah' => $terkini->stok,
            ]);

            return $terkini;
        });

        $diperbarui->load(['kategori', 'toko']);

        return $this->sukses(ProdukSayaResource::make($diperbarui));
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
