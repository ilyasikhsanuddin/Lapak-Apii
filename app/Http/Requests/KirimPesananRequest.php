<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class KirimPesananRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nomor_resi' => ['required', 'string', 'min:5', 'max:50', 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->nomor_resi)) {
            $this->merge([
                'nomor_resi' => strtoupper(trim($this->nomor_resi)),
            ]);
        }
    }
}
