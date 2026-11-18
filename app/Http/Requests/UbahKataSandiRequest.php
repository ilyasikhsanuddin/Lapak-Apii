<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class UbahKataSandiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kata_sandi_baru' => ['required', 'string', 'max:72', Password::min(10)->letters()->numbers()],
            'konfirmasi_kata_sandi_baru' => ['required', 'same:kata_sandi_baru'],
        ];
    }

    public function messages(): array
    {
        return [
            'konfirmasi_kata_sandi_baru.same' => 'Konfirmasi kata sandi tidak cocok.',
        ];
    }
}
