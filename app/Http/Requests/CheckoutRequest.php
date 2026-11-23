<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alamat_id' => [
                'required',
                'integer',
                Rule::exists('alamat', 'id')
                    ->where('pengguna_id', $this->user()->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'alamat_id.exists' => 'Alamat tidak ditemukan.',
        ];
    }
}
