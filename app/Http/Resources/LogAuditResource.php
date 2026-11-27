<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LogAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu baris log audit seperti yang dilihat auditor.
 * Hanya kolom yang memang disimpan; tidak ada rahasia karena PencatatAudit menolaknya di pintu.
 *
 * @mixin LogAudit
 */
final class LogAuditResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'peristiwa' => $this->peristiwa,
            'pelaku_id' => $this->pelaku_id,
            'sasaran' => $this->sasaran,
            'rincian' => $this->rincian,
            'id_permintaan' => $this->id_permintaan,
            'dicatat_pada' => $this->dicatat_pada->toIso8601String(),
            'hash' => $this->hash,
        ];
    }
}
