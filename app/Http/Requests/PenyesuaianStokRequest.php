<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\PenjagaStok;
use Illuminate\Foundation\Http\FormRequest;

final class PenyesuaianStokRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $batas = PenjagaStok::MAKSIMUM;

        return [
            'selisih' => ['required', 'integer', 'not_in:0', "between:-{$batas},{$batas}"],
        ];
    }
}
