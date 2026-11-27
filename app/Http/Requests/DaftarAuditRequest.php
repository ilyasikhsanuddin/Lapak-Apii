<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PeristiwaAudit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Penyaring GET /audit. Izin diperiksa di rute (audit.lihat), bukan di sini. */
final class DaftarAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'peristiwa' => ['sometimes', 'string', Rule::enum(PeristiwaAudit::class)],
            'sasaran' => ['sometimes', 'string', 'max:80'],
            'pelaku_id' => ['sometimes', 'integer', 'min:1'],
            'halaman' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
