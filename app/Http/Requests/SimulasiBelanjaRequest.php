<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SimulasiBelanjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'butir' => [
                'required',
                'array',
                'min:1',
                'max:'.config('lapak.belanja.butir_maksimum'),
            ],
            'butir.*.produk_id' => [
                'required',
                'integer',
                'min:1',
                'distinct',
            ],
            'butir.*.kuantitas' => [
                'required',
                'integer',
                'between:1,'.config('lapak.belanja.kuantitas_maksimum'),
            ],
        ];
    }

    /**
     * @return list<array{produk_id: int, kuantitas: int}>
     */
    public function butir(): array
    {
        return array_map(
            fn (array $item): array => [
                'produk_id' => (int) $item['produk_id'],
                'kuantitas' => (int) $item['kuantitas'],
            ],
            $this->validated('butir'),
        );
    }
}
