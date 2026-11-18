<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TokoSayaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $wajib = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'nama' => [$wajib, 'string', 'min:3', 'max:80'],
            'kota' => [$wajib, 'string', 'min:2', 'max:60'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'ongkir_rupiah' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
        ];
    }
}
