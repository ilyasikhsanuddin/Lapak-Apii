<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\StatusProduk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProdukSayaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $wajib = $this->isMethod('POST') ? 'required' : 'sometimes';
        $aturanStok = $this->isMethod('POST')
            ? ['required', 'integer', 'between:0,100000']
            : ['prohibited'];

        return [
            'nama' => [$wajib, 'string', 'min:3', 'max:120'],
            'kategori_id' => [$wajib, 'integer', Rule::exists('kategori', 'id')],
            'deskripsi' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'harga_rupiah' => [$wajib, 'integer', 'between:100,1000000000'],
            'stok' => $aturanStok,
            'berat_gram' => ['sometimes', 'integer', 'between:0,100000'],
            'status' => ['sometimes', Rule::enum(StatusProduk::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'stok.prohibited' => 'Stok tidak dapat diubah lewat PATCH; gunakan POST /api/v1/toko-saya/produk/{id}/stok.',
        ];
    }
}
