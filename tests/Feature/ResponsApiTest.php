<?php

declare(strict_types=1);

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

function buatControllerUji(): Controller
{
    return new class extends Controller
    {
        public function panggilSukses(mixed $data = null, int $status = 200, array $meta = []): JsonResponse
        {
            return $this->sukses($data, $status, $meta);
        }
    };
}

it('membungkus data dalam amplop sukses dengan meta null', function () {
    $respons = buatControllerUji()->panggilSukses(['nama' => 'Kopi'], 201);

    expect($respons->getStatusCode())->toBe(201)
        ->and($respons->getData(true))->toBe([
            'sukses' => true,
            'data' => ['nama' => 'Kopi'],
            'meta' => null,
        ]);
});

it('menyertakan meta kalau diberikan', function () {
    $respons = buatControllerUji()->panggilSukses([], 200, ['total' => 5]);

    expect($respons->getData(true)['meta'])->toBe(['total' => 5]);
});
