<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Pencairan;
use Illuminate\Http\Resources\Json\JsonResource;

class PencairanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        /** @var Pencairan $this */
        return [
            'kode' => $this->kode,
            'status' => $this->status->value,
            'jumlah_rupiah' => $this->jumlah_rupiah,
            'rekening_tujuan' => $this->rekening_tujuan,
            'pengaju_id' => $this->pengaju_id,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
