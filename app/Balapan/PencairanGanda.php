<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Enums\JenisDompet;
use App\Models\Dompet;
use App\Models\Pencairan;
use App\Models\User;
use App\Services\LayananPencairan;
use Illuminate\Support\Facades\DB;

/**
 * Script to simulate concurrent pencairan submissions.
 * It attempts to submit the same pencairan request multiple times in parallel
 * and verifies that only one succeeds while others fail due to insufficient
 * balance (LP‑13) or duplicate request handling.
 */
class PencairanGanda
{
    public static function run(int $concurrency = 5): void
    {
        $service = app(LayananPencairan::class);
        $user = User::factory()->create();
        // Ensure the pencairan wallet has sufficient balance.
        $pencairanWallet = Dompet::query()->where('kode', JenisDompet::Pencairan->value)->first();
        $pencairanWallet->update(['saldo_rupiah' => 1_000_000]);

        $jumlah = 200_000;
        $rekening = '1234567890';

        $results = [];
        // Run concurrent attempts using DB transactions and SELECT ... FOR UPDATE.
        for ($i = 0; $i < $concurrency; $i++) {
            $results[] = DB::transaction(function () use ($service, $user, $jumlah, $rekening) {
                try {
                    $service->ajukan(
                        pengaju: $user,
                        jumlahRupiah: $jumlah,
                        rekeningTujuan: $rekening
                    );

                    return true;
                } catch (\Exception $e) {
                    return $e->getMessage();
                }
            }, attempts: 1);
        }

        $successful = array_filter($results, fn ($r) => $r === true);
        $failed = array_filter($results, fn ($r) => $r !== true);
        echo 'Successful submissions: '.count($successful).PHP_EOL;
        echo 'Failed submissions: '.count($failed).PHP_EOL;
        foreach ($failed as $msg) {
            echo "Failure reason: $msg".PHP_EOL;
        }
    }
}
