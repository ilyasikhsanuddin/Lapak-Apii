<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Uang;
use App\Models\Dompet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dompet seperti yang dilihat staf pemegang izin buku-besar.lihat.
 * Resource ini memuat pengenal dompet dan pemiliknya, maka tidak pernah dipakai pintu /dompet.
 *
 * @mixin Dompet
 */
final class DompetBukuBesarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode' => $this->kode,
            'jenis' => $this->jenis->value,
            'pemilik' => $this->whenLoaded('pemilik', fn () => $this->pemilik === null ? null : [
                'id' => $this->pemilik->id,
                'nama' => $this->pemilik->nama,
            ]),
            'saldo' => [
                // Dompet eksternal boleh negatif, sedangkan Uang hanya mengenal nilai tak negatif.
                'rupiah' => $this->saldo_rupiah,
                'tampilan' => ($this->saldo_rupiah < 0 ? '-' : '')
                    .Uang::dari(abs($this->saldo_rupiah))->format(),
            ],
        ];
    }
}
