<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MutasiSaldo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mutasi untuk staf: sama dengan yang dilihat pemilik dompet, ditambah nomor baris dan kelompok.
 *
 * @mixin MutasiSaldo
 */
final class MutasiBukuBesarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kelompok' => $this->kelompok,
            ...MutasiResource::make($this->resource)->toArray($request),
        ];
    }
}
