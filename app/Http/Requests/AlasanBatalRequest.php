<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AlasanBatalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function alasan(): string
    {
        return trim((string) $this->validated('alasan'));
    }

    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
